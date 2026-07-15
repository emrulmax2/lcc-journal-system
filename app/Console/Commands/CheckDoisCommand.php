<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DepositItemStatus;
use App\Models\DoiDepositItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Resolves every REGISTERED DOI and reports any that no longer land anywhere.
 *
 * Link rot is the failure mode that destroys a journal's credibility, and it happens
 * silently: nobody browsing the site would ever notice that a DOI minted two years ago
 * now 404s, because nothing on the site links to it — only other people's citations do.
 *
 * Schedule this. It is cheap and it is the only thing that will tell you.
 */
class CheckDoisCommand extends Command
{
    protected $signature = 'journal:check-dois {--limit=0 : Only check the first N}';

    protected $description = 'Resolve every registered DOI and report any that no longer resolve';

    public function handle(): int
    {
        $query = DoiDepositItem::query()
            ->where('status', DepositItemStatus::Registered)
            ->with('article')
            ->orderBy('id');

        if ($limit = (int) $this->option('limit')) {
            $query->limit($limit);
        }

        $items = $query->get();

        if ($items->isEmpty()) {
            $this->info('No registered DOIs yet. Nothing to check.');

            return self::SUCCESS;
        }

        $this->line("Resolving {$items->count()} DOI(s)…");

        $broken = [];

        foreach ($items as $item) {
            $target = $item->article?->landingUrl();

            if ($target === null) {
                // The DOI is registered but the article it points at is gone from our
                // database entirely. That is the worst case: the promise is live and we
                // cannot keep it.
                $broken[] = [$item->doi, 'ARTICLE MISSING', 'the article record no longer exists'];

                continue;
            }

            try {
                // Check OUR landing page, not doi.org. If our page is down, the DOI is
                // broken regardless of what Crossref thinks — and doi.org's redirect
                // would report success while sending readers to a 404.
                $response = Http::timeout(15)->head($target);

                if (! $response->successful()) {
                    $broken[] = [$item->doi, "HTTP {$response->status()}", $target];
                }
            } catch (\Throwable $e) {
                $broken[] = [$item->doi, 'UNREACHABLE', $e->getMessage()];
            }
        }

        if ($broken === []) {
            $this->info("OK — all {$items->count()} registered DOIs resolve.");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error(count($broken).' DOI(s) DO NOT RESOLVE:');
        $this->table(['DOI', 'Problem', 'Detail'], $broken);
        $this->newLine();
        $this->line('These are broken promises. Other people have cited them in work they cannot now correct.');

        return self::FAILURE;
    }
}
