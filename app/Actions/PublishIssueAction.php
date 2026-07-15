<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ArticleStatus;
use App\Enums\IssueStatus;
use App\Jobs\DepositToCrossref;
use App\Models\Issue;
use App\Services\Doi\DoiSuffixGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publishes an entire issue: every article in it goes live together, and the whole issue
 * is deposited to Crossref as one batch.
 *
 * Same rules as PublishArticleAction — every pre-flight failure at once, one atomic
 * transaction, and the Crossref deposit dispatched OUTSIDE it so that a Crossref outage
 * cannot roll back a publication.
 *
 * The pre-flight runs across ALL articles before publishing ANY of them. A partially
 * published issue — half the articles live, half not, page numbers referring to articles
 * nobody can read — is worse than an unpublished one.
 */
final class PublishIssueAction
{
    public function __construct(private readonly DoiSuffixGenerator $suffixes) {}

    /** @throws ValidationException */
    public function execute(Issue $issue): Issue
    {
        $issue->load(['volume.journal', 'articles.authors', 'articles.section', 'articles.pdf']);

        $this->preflight($issue);

        $articleIds = $issue->articles->pluck('id')->all();

        DB::transaction(function () use ($issue): void {
            foreach ($issue->articles as $article) {
                if (blank($article->doi_suffix)) {
                    $article->doi_suffix = $this->suffixes->generate(
                        $article->setRelation('journal', $issue->journal)->setRelation('issue', $issue)
                    );
                }

                $article->status = ArticleStatus::Published;
                $article->published_at ??= $issue->publication_date ?? now();
                $article->save();
            }

            $issue->status = IssueStatus::Published;
            $issue->publication_date ??= now()->toDateString();
            $issue->save();
        });

        // Outside the transaction. One batch for the whole issue — that is how Crossref
        // models an issue-based journal, and it means one deposit record to retry rather
        // than N.
        if ($issue->journal->canMintDois() && $articleIds !== []) {
            DepositToCrossref::dispatch($issue->journal->id, $articleIds, $issue->id);
        }

        return $issue->refresh();
    }

    /** @throws ValidationException */
    private function preflight(Issue $issue): void
    {
        $errors = [];

        if ($issue->isPublished()) {
            $errors['issue'][] = 'This issue is already published. A published issue is immutable.';
        }

        if ($issue->articles->isEmpty()) {
            $errors['articles'][] = 'The issue has no articles.';
        }

        // Validate every article, and prefix each failure with the article it belongs to,
        // so the editor gets one complete list rather than a maze.
        foreach ($issue->articles as $article) {
            $label = 'Article '.($article->sequence ?? '?').' ("'.str($article->title)->limit(40).'")';

            if (blank($article->abstract)) {
                $errors['articles'][] = "{$label} has no abstract.";
            }

            if (! $article->hasPdf()) {
                $errors['articles'][] = "{$label} has no PDF.";
            }

            if ($article->authors->isEmpty() && ! $article->hasCorporateAuthor()) {
                $errors['articles'][] = "{$label} has no author.";
            }

            if ($article->first_page === null || $article->last_page === null) {
                $errors['articles'][] = "{$label} has no page range.";
            }
        }

        $this->checkSequences($issue, $errors);
        $this->checkPageRanges($issue, $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param  array<string, list<string>>  $errors */
    private function checkSequences(Issue $issue, array &$errors): void
    {
        $sequences = $issue->articles->pluck('sequence')->filter()->all();
        $duplicates = array_keys(array_filter(array_count_values($sequences), fn (int $n) => $n > 1));

        foreach ($duplicates as $sequence) {
            // Two articles at the same position derive the same DOI suffix. The unique
            // index would catch it — as a 500, halfway through publishing the issue.
            $errors['sequence'][] = "Two articles share position {$sequence}. They would derive the same DOI.";
        }
    }

    /** @param  array<string, list<string>>  $errors */
    private function checkPageRanges(Issue $issue, array &$errors): void
    {
        $ranges = $issue->articles
            ->filter(fn ($a) => $a->first_page !== null && $a->last_page !== null)
            ->sortBy('first_page')
            ->values();

        for ($i = 1; $i < $ranges->count(); $i++) {
            $previous = $ranges[$i - 1];
            $current = $ranges[$i];

            if ($current->first_page <= $previous->last_page) {
                $errors['pages'][] = sprintf(
                    'Pages overlap: "%s" (%d–%d) and "%s" (%d–%d).',
                    str($previous->title)->limit(30),
                    $previous->first_page,
                    $previous->last_page,
                    str($current->title)->limit(30),
                    $current->first_page,
                    $current->last_page,
                );
            }
        }
    }
}
