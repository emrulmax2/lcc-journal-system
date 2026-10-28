<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ArticleStatus;
use App\Enums\IssueStatus;
use App\Jobs\DepositToCrossref;
use App\Models\Article;
use App\Models\Issue;
use App\Models\Journal;
use App\Services\Doi\DoiSuffixGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Publishes the imported print back-catalogue ON ITS ORIGINAL DATES.
 *
 * These issues were published years ago on lcc.ac.uk; this puts them live here with the
 * date each one actually came out, never today's. The dates live in each archive
 * issue.json ("publication_date", with "publication_date_source" saying where it came from),
 * so they are reviewable data, not constants buried in code.
 *
 * Why not PublishIssueAction? Its pre-flight requires an abstract on every article, and the
 * back-catalogue's editorials and commentaries were printed without one. Inventing an
 * abstract for a published editorial would be fabricating the record. Everything else is
 * held to the same rules: a PDF, authors (or a corporate author, never both), a page range,
 * unique positions and no overlapping pages — checked for the WHOLE issue before ANY of it
 * goes live. Like PublishIssueAction, this mints the DOI suffix from the one generator and
 * freezes slug / sequence / doi_suffix for good.
 */
class PublishArchiveCommand extends Command
{
    protected $signature = 'journal:publish-archive
        {path : The jcdms-archive folder}
        {--journal=jcdms : Slug of the journal}
        {--issue=* : Only these issue folders, e.g. --issue=vol05-no1-2017}
        {--dry-run : Check everything and show the dates without publishing}';

    protected $description = 'Publish the imported back-catalogue issues on their original publication dates';

    public function handle(DoiSuffixGenerator $suffixes): int
    {
        $root = rtrim(str_replace('\\', '/', (string) $this->argument('path')), '/');
        $journal = Journal::where('slug', $this->option('journal'))->first();

        if (! $journal instanceof Journal) {
            $this->error("No journal with slug '{$this->option('journal')}'.");

            return self::FAILURE;
        }

        $files = collect(glob($root.'/vol*/issue.json') ?: [])
            ->when($this->option('issue') !== [], fn ($c) => $c->filter(
                fn (string $f) => in_array(basename(dirname($f)), $this->option('issue'), true)
            ))
            ->sort()->values();

        if ($files->isEmpty()) {
            $this->error("No issue folders found in {$root}.");

            return self::FAILURE;
        }

        $failed = false;

        foreach ($files as $file) {
            $failed = ! $this->publishIssue($journal, $file, $suffixes) || $failed;
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('DRY RUN — nothing was published.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function publishIssue(Journal $journal, string $file, DoiSuffixGenerator $suffixes): bool
    {
        $meta = json_decode((string) file_get_contents($file), true);
        $label = "Vol {$meta['volume']} No {$meta['number']} ({$meta['season']})";

        $this->newLine();

        if (blank($meta['publication_date'] ?? null)) {
            $this->error("{$label}: no publication_date in {$file} — refusing to publish with today's date.");

            return false;
        }

        $date = Carbon::parse($meta['publication_date'])->startOfDay();

        if ($date->isFuture()) {
            $this->error("{$label}: publication_date {$date->toDateString()} is in the future.");

            return false;
        }

        $issue = Issue::query()
            ->whereHas('volume', fn ($q) => $q->where('journal_id', $journal->id)->where('number', $meta['volume']))
            ->where('number', $meta['number'])
            ->with(['volume', 'articles.authors', 'articles.pdf'])
            ->first();

        if (! $issue instanceof Issue) {
            $this->error("{$label}: not in the database. Run journal:import-archive first.");

            return false;
        }

        $drafts = $issue->articles->filter(fn (Article $a) => ! $a->isFrozen())->sortBy('sequence')->values();
        $this->line("<options=bold>{$label}</> → {$date->format('j M Y')}  ·  {$drafts->count()} to publish");

        $errors = $this->preflight($issue->articles, $drafts);

        if ($errors !== []) {
            $this->error('  Not published — fix these first:');
            foreach ($errors as $e) {
                $this->line("    - {$e}");
            }

            return false;
        }

        $noAbstract = $drafts->filter(fn (Article $a) => blank($a->abstract))->count();
        if ($noAbstract > 0) {
            $this->line("  <fg=gray>{$noAbstract} published without an abstract (as printed).</>");
        }

        if ($this->option('dry-run')) {
            return true;
        }

        DB::transaction(function () use ($journal, $issue, $drafts, $date, $suffixes): void {
            foreach ($drafts as $article) {
                if (blank($article->doi_suffix)) {
                    $article->doi_suffix = $suffixes->generate(
                        $article->setRelation('journal', $journal)->setRelation('issue', $issue)
                    );
                }

                $article->status = ArticleStatus::Published;
                $article->published_at = $date;
                $article->save();
            }

            $issue->status = IssueStatus::Published;
            $issue->publication_date = $date->toDateString();
            $issue->save();
        });

        // Outside the transaction, exactly as PublishIssueAction does. A no-op until
        // Crossref issues the journal a prefix.
        if ($journal->canMintDois() && $drafts->isNotEmpty()) {
            DepositToCrossref::dispatch($journal->id, $drafts->pluck('id')->all(), $issue->id);
        }

        $this->info("  Published {$drafts->count()} articles.");

        return true;
    }

    /**
     * Every problem in the issue at once, before anything is published.
     *
     * @param  Collection<int, Article>  $all
     * @param  Collection<int, Article>  $drafts
     * @return list<string>
     */
    private function preflight(Collection $all, Collection $drafts): array
    {
        $errors = [];

        foreach ($drafts as $a) {
            $name = '#'.($a->sequence ?? '?').' "'.str($a->title)->limit(45).'"';

            if (blank($a->title)) {
                $errors[] = "{$name} has no title.";
            }
            if ($a->pdf === null) {
                $errors[] = "{$name} has no PDF.";
            }
            if ($a->authors->isEmpty() && blank($a->corporate_author)) {
                $errors[] = "{$name} has no author.";
            }
            if ($a->authors->isNotEmpty() && filled($a->corporate_author)) {
                $errors[] = "{$name} has both named authors and a corporate author.";
            }
            if ($a->sequence === null || $a->sequence < 1) {
                $errors[] = "{$name} has no position in the issue.";
            }
            if ($a->first_page === null || $a->last_page === null || $a->last_page < $a->first_page) {
                $errors[] = "{$name} has no valid page range.";
            }
        }

        foreach ($all->countBy('sequence')->filter(fn (int $n) => $n > 1) as $seq => $n) {
            $errors[] = "{$n} articles share position {$seq} — they would get the same DOI.";
        }

        $ranged = $all->filter(fn (Article $a) => $a->first_page !== null && $a->last_page !== null)->sortBy('first_page')->values();
        for ($i = 1; $i < $ranged->count(); $i++) {
            if ($ranged[$i]->first_page <= $ranged[$i - 1]->last_page) {
                $errors[] = "Pages overlap: #{$ranged[$i - 1]->sequence} ({$ranged[$i - 1]->first_page}–{$ranged[$i - 1]->last_page}) "
                    ."and #{$ranged[$i]->sequence} ({$ranged[$i]->first_page}–{$ranged[$i]->last_page}).";
            }
        }

        return $errors;
    }
}
