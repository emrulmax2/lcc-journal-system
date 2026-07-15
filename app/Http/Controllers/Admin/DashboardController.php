<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleStatus;
use App\Enums\DepositStatus;
use App\Enums\IssueStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Journal;
use App\Models\User;
use App\Support\AdminChrome;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The editorial admin's front door: one card per journal this person actually works on.
 *
 * The DOI line is the one that matters. A journal with no `doi_prefix` is not broken and
 * not misconfigured — Crossref has simply not issued one yet, and until it does, DOIs
 * cannot be registered. That is said in words, on the card, because the alternative is an
 * editor seeing an empty DOI column and filing a bug, or worse, "fixing" it by typing a
 * prefix that belongs to somebody else.
 */
final class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $journals = AdminChrome::editorialJournals($user);

        // Not an error page for a missing feature — an honest 403. A reviewer or an author
        // carries `journal.view`, and neither has any business in the editorial admin.
        abort_if($journals->isEmpty(), 403, 'You do not have an editorial role on any journal.');

        return Inertia::render('Admin/Dashboard', [
            'journals' => $journals->map(fn (Journal $journal): array => $this->overview($journal, $user))->all(),

            // Site-wide, so it hangs off the page rather than off a journal card.
            'canManageSiteContent' => $user->can('manage-site-content'),

            'meta' => [
                'title' => 'Editorial admin — '.config('app.name'),
                'description' => 'Journals, issues, publication and DOI registration.',
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function overview(Journal $journal, User $user): array
    {
        $articles = $journal->articles();

        return [
            'id' => $journal->id,
            'slug' => $journal->slug,
            'title' => $journal->title,
            'abbreviation' => $journal->abbreviation,
            'publicationModel' => $journal->publication_model->value,
            'usesIssues' => $journal->usesIssues(),

            'counts' => [
                'draftArticles' => (clone $articles)->where('status', ArticleStatus::Draft)->count(),
                'publishedArticles' => (clone $articles)->where('status', ArticleStatus::Published)->count(),

                // Drafts are excluded by scopeActive's sibling, visibleToEditors: nothing
                // reaches an editor until the author sends it, and counting unsent drafts
                // in an editor's queue would break that promise in the very first tile.
                'openSubmissions' => $journal->submissions()->visibleToEditors()->active()->count(),

                'failedDeposits' => $journal->deposits()->where('status', DepositStatus::Failed)->count(),

                'draftIssues' => $journal->usesIssues()
                    ? $journal->volumes()->withCount(['issues' => fn ($q) => $q->where('status', IssueStatus::Draft)])
                        ->get()
                        ->sum('issues_count')
                    : 0,
            ],

            // THE DELIBERATE NULL. Not an error state, not a placeholder — the true state of
            // a journal Crossref has not registered yet. Article::doi() returns NULL, the
            // deposit job is never dispatched, and nothing anywhere invents a prefix.
            'doi' => [
                'prefix' => $journal->doi_prefix,
                'canMintDois' => $journal->canMintDois(),
                'suffixPattern' => $journal->doi_suffix_pattern,
            ],

            // The way back into an article that is not in an issue — which for a continuous
            // journal is EVERY article, and for an issue-based one is every article not yet
            // placed. Without this there is no route to them at all.
            'recentArticles' => $journal->articles()
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get()
                ->map(fn (Article $article): array => [
                    'id' => $article->id,
                    'title' => $article->title,
                    'status' => $article->status->value,
                    'statusLabel' => $article->status->label(),
                    'isPublished' => $article->isPublished(),
                    'updatedAt' => $article->updated_at?->toIso8601String(),
                ])
                ->all(),

            'can' => [
                'manageArticles' => $user->can('manageArticles', $journal),
                'manageIssues' => $user->can('manageIssues', $journal),
                'manageSettings' => $user->can('manageSettings', $journal),
                'manageUsers' => $user->can('manageUsers', $journal),
                'depositDois' => $user->can('depositDois', $journal),
                'publish' => $user->can('publish', $journal),

                // Site-wide, not journal-scoped — see AdminChrome. It is the same answer on
                // every card, which is exactly what "the privacy policy is not JCD&MS's" means.
                'manageSiteContent' => $user->can('manage-site-content'),
            ],
        ];
    }
}
