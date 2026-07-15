<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ArticleStatus;
use App\Jobs\DepositToCrossref;
use App\Models\Article;
use App\Services\Doi\DoiSuffixGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * THE HIGHEST-RISK OPERATION IN THE SYSTEM.
 *
 * Publishing makes a URL permanent, freezes an identifier, and (downstream) spends money
 * at Crossref minting a DOI that can never be withdrawn. There is no undo. So:
 *
 *  1. PRE-FLIGHT RETURNS EVERY PROBLEM AT ONCE. Not the first. An editor fixing a
 *     publication one error at a time, with a real deadline, is how a half-complete
 *     article goes live.
 *
 *  2. THE STATE CHANGE IS ATOMIC. Status, timestamp and identifiers are one transaction.
 *
 *  3. THE CROSSREF DEPOSIT IS DISPATCHED OUTSIDE IT. If Crossref is unreachable, its
 *     credentials have lapsed, or it rejects our XML, THE PAGES STILL GO LIVE and stay
 *     live; the editor retries the deposit from the deposit log. The public site must
 *     never depend on Crossref being up. Do not "helpfully" move the dispatch inside the
 *     transaction — a Crossref outage would then roll back a publication that had already
 *     been announced.
 */
final class PublishArticleAction
{
    public function __construct(private readonly DoiSuffixGenerator $suffixes) {}

    /** @throws ValidationException with EVERY pre-flight failure, not just the first. */
    public function execute(Article $article): Article
    {
        $article->loadMissing(['journal', 'issue.volume', 'authors', 'section', 'pdf']);

        $this->preflight($article);

        DB::transaction(function () use ($article): void {
            // Mint the suffix if it doesn't have one yet. After this save it is frozen
            // forever — ArticleObserver will refuse every subsequent change.
            if (blank($article->doi_suffix)) {
                $article->doi_suffix = $this->suffixes->generate($article);
            }

            $article->status = ArticleStatus::Published;
            $article->published_at ??= now();

            $article->save();
        });

        // OUTSIDE the transaction, deliberately. See the class docblock.
        if ($article->journal->canMintDois()) {
            DepositToCrossref::dispatch($article->journal->id, [$article->id]);
        }

        return $article->refresh();
    }

    /**
     * Collect ALL failures, then throw once.
     *
     * @throws ValidationException
     */
    private function preflight(Article $article): void
    {
        $errors = [];

        if (blank($article->title)) {
            $errors['title'][] = 'The article needs a title.';
        }

        if (blank($article->abstract)) {
            $errors['abstract'][] = 'The article needs an abstract. Indexes and Crossref both require one.';
        }

        if (! $article->hasPdf()) {
            $errors['pdf'][] = 'The article needs a PDF. citation_pdf_url is advertised to Google Scholar, '
                .'and an advertised PDF that 404s downgrades the whole journal.';
        }

        // Either named people OR a corporate author. Never neither: an authorless article
        // will not be indexed by Scholar, and it cannot be cited.
        if ($article->authors->isEmpty() && ! $article->hasCorporateAuthor()) {
            $errors['authors'][] = 'The article needs at least one author, or a corporate author '
                .'(for an editorial by a research centre).';
        }

        if ($article->authors->isNotEmpty() && $article->hasCorporateAuthor()) {
            $errors['authors'][] = 'The article has both named authors and a corporate author. '
                .'Crossref accepts one or the other, not both — pick one.';
        }

        if ($article->journal->usesIssues()) {
            $this->preflightIssueBased($article, $errors);
        }

        if (blank($article->journal->doi_prefix)) {
            // NOT fatal. The article can be published and the DOI deposited later, once
            // Crossref issues the prefix. This is a warning surfaced in the UI, not a
            // blocker — otherwise nothing could ever be published before registration.
            // (Recorded here so the caller can show it; it does not enter $errors.)
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param  array<string, list<string>>  $errors */
    private function preflightIssueBased(Article $article, array &$errors): void
    {
        if ($article->issue === null) {
            $errors['issue'][] = 'This journal publishes in issues. The article must be placed in one.';

            return;
        }

        if ($article->sequence === null) {
            $errors['sequence'][] = 'The article needs a position in the issue — the DOI suffix is derived from it.';
        }

        if ($article->first_page === null || $article->last_page === null) {
            $errors['pages'][] = 'The article needs a page range.';
        }

        if ($article->first_page !== null
            && $article->last_page !== null
            && $article->last_page < $article->first_page) {
            $errors['pages'][] = "The page range ends before it starts ({$article->first_page}–{$article->last_page}).";
        }

        // A duplicate sequence within an issue means two articles would derive the SAME
        // DOI suffix. The unique index would catch it — as a 500 — but only for whichever
        // one published second, and only after the first was already live.
        $duplicateSequence = Article::query()
            ->where('issue_id', $article->issue_id)
            ->where('sequence', $article->sequence)
            ->whereKeyNot($article->id)
            ->exists();

        if ($duplicateSequence) {
            $errors['sequence'][] = "Another article in this issue is already at position {$article->sequence}. "
                .'Two articles at the same position would derive the same DOI.';
        }

        $this->checkOverlappingPages($article, $errors);
    }

    /** @param  array<string, list<string>>  $errors */
    private function checkOverlappingPages(Article $article, array &$errors): void
    {
        if ($article->first_page === null || $article->last_page === null) {
            return;
        }

        $overlapping = Article::query()
            ->where('issue_id', $article->issue_id)
            ->whereKeyNot($article->id)
            ->whereNotNull('first_page')
            ->whereNotNull('last_page')
            // Two ranges overlap when each starts before the other ends.
            ->where('first_page', '<=', $article->last_page)
            ->where('last_page', '>=', $article->first_page)
            ->get(['id', 'title', 'first_page', 'last_page']);

        foreach ($overlapping as $other) {
            $errors['pages'][] = sprintf(
                'Pages %d–%d overlap with "%s" (pages %d–%d).',
                $article->first_page,
                $article->last_page,
                str($other->title)->limit(40),
                $other->first_page,
                $other->last_page,
            );
        }
    }
}
