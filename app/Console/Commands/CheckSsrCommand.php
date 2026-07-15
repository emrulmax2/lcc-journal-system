<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Asserts that a published article page is READABLE BY A MACHINE.
 *
 * This command exists because of a specific, nasty failure mode. When the Inertia SSR
 * process dies, Inertia does not error — it falls back to client-side rendering. The
 * site keeps working perfectly for every human who looks at it, including whoever is
 * checking that the site is up. Meanwhile every crawler receives an empty <div id="app">,
 * Google Scholar quietly drops the journal, and the DOIs we paid to register resolve to
 * pages that no index can read. There is no error, no exception, no alert. It is the
 * single most dangerous thing that can happen to this system, and it looks like nothing.
 *
 * So: schedule this. Do not rely on the SSR process reporting its own health, because
 * the whole problem is that it fails by being absent.
 */
class CheckSsrCommand extends Command
{
    protected $signature = 'journal:check-ssr {--url= : Check a specific URL instead of a seeded article}';

    protected $description = 'Assert that public pages render server-side with citation metadata intact';

    public function handle(): int
    {
        $url = $this->option('url') ?: $this->firstPublishedArticleUrl();

        if ($url === null) {
            $this->warn('No published article to check. Publish one, then run this again.');

            return self::SUCCESS;
        }

        $this->line("Fetching (no JavaScript): {$url}");

        try {
            $response = Http::timeout(20)
                // Announce ourselves honestly. Do NOT spoof Googlebot — if the app ever
                // special-cases a user agent, this check would validate a code path that
                // no real crawler takes, and we would be testing a lie.
                ->withHeaders(['User-Agent' => 'MeridianSsrHealthCheck/1.0 (+journal@lcc.ac.uk)'])
                ->get($url);
        } catch (\Throwable $e) {
            $this->error('Request failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $html = $response->body();
        $failures = [];

        if (! $response->successful()) {
            $failures[] = "HTTP {$response->status()} (expected 200)";
        }

        // 1. The DOI-critical metadata. Rendered by Blade, in PHP — so this must hold
        //    even when the Node SSR process is dead. If THIS fails, something has moved
        //    the meta tags into React, and the DOI programme is broken.
        foreach (['citation_title', 'citation_journal_title', 'citation_author', 'citation_abstract_html_url'] as $tag) {
            if (! str_contains($html, 'name="'.$tag.'"')) {
                $failures[] = "missing meta tag: {$tag}";
            }
        }

        // 2. The body content. This is what depends on the Node SSR process. An empty
        //    app div means SSR is down and we are serving a blank page to every crawler.
        if (preg_match('/<div id="app"[^>]*>\s*<\/div>/', $html)) {
            $failures[] = 'SSR IS DOWN — <div id="app"> is empty. Humans see a working site; '
                .'crawlers see nothing. Start the SSR process (php artisan inertia:start-ssr).';
        }

        // 3. The invisibility trap. framer-motion serialises `initial` variants into the
        //    style attribute, so a stray initial="hidden" ships content at opacity:0.
        //    The page would contain every word and still be unreadable.
        if (preg_match('/<(main|section|div)[^>]*style="[^"]*opacity:\s*0/i', $html)) {
            $failures[] = 'content rendered at opacity:0 — a framer-motion `initial` variant '
                .'has leaked into the server render. See Reveal.tsx / Layout.tsx.';
        }

        if ($failures !== []) {
            $this->newLine();
            $this->error('FAILED — this page is not machine-readable:');
            foreach ($failures as $failure) {
                $this->line('  • '.$failure);
            }
            $this->newLine();
            $this->line('Every DOI pointing at this page is currently pointing at nothing a crawler can read.');

            return self::FAILURE;
        }

        $tags = preg_match_all('/name="(citation_[a-z_]+)"/', $html, $m) ? array_unique($m[1]) : [];

        $this->info('OK — page is server-rendered and machine-readable.');
        $this->line('  citation tags present: '.implode(', ', $tags));
        $this->line('  body bytes: '.number_format(strlen($html)));

        return self::SUCCESS;
    }

    private function firstPublishedArticleUrl(): ?string
    {
        return Article::published()->first()?->landingUrl();
    }
}
