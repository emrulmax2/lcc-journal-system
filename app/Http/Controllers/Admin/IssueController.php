<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\IssueStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\Volume;
use App\Services\Doi\DoiSuffixGenerator;
use App\Support\AdminChrome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Volumes and issues — the issue-based journal's archive.
 *
 * A CONTINUOUS JOURNAL HAS NO ISSUES AT ALL, so this screen does not exist for one. Not a
 * disabled tab, not an empty state: a 404. Volumes and issues are a print artefact, and
 * rendering the furniture of one publication model inside the other teaches editors that
 * the model is a setting rather than a fact about the journal.
 *
 * A PUBLISHED ISSUE IS IMMUTABLE. That is enforced by IssuePolicy, and every refusal here
 * comes from it rather than from a second copy of the rule.
 */
final class IssueController extends Controller
{
    public function index(Request $request, Journal $journal): Response
    {
        $this->authorize('manageIssues', $journal);

        abort_unless($journal->usesIssues(), 404);

        $volumes = $journal->volumes()
            ->with(['issues' => fn ($q) => $q->withCount('articles')])
            ->orderByDesc('number')
            ->get();

        return Inertia::render('Admin/Issues', array_merge(
            AdminChrome::for($request->user(), $journal),
            [
                'volumes' => $volumes->map(fn (Volume $volume): array => [
                    'id' => $volume->id,
                    'number' => $volume->number,
                    'year' => $volume->year,
                    'issues' => $volume->issues->map(fn (Issue $issue): array => [
                        'id' => $issue->id,
                        'number' => $issue->number,
                        'season' => $issue->season,
                        'status' => $issue->status->value,
                        'statusLabel' => $issue->status->label(),
                        'publicationDate' => $issue->publication_date?->toDateString(),
                        'articles' => $issue->articles_count,
                        'isPublished' => $issue->isPublished(),
                    ])->values()->all(),
                ])->values()->all(),

                'meta' => [
                    'title' => 'Issues — '.$journal->title,
                    'description' => 'Volumes, issues and their running order.',
                ],
            ],
        ));
    }

    public function show(Request $request, Issue $issue): Response
    {
        $issue->load(['volume.journal', 'articles.authors', 'articles.section', 'articles.pdf']);

        $journal = $issue->journal;
        abort_if($journal === null, 404);

        // The JOURNAL-scoped ability, not the issue-scoped one: IssuePolicy::manageArticles
        // is false on a published issue, and a published issue must still be READABLE here.
        // What it must not be is editable — see reorder().
        $this->authorize('manageArticles', $journal);

        return Inertia::render('Admin/IssueDetail', array_merge(
            AdminChrome::for($request->user(), $journal),
            [
                'issue' => [
                    'id' => $issue->id,
                    'number' => $issue->number,
                    'season' => $issue->season,
                    'status' => $issue->status->value,
                    'statusLabel' => $issue->status->label(),
                    'publicationDate' => $issue->publication_date?->toDateString(),
                    'isPublished' => $issue->isPublished(),
                    'volume' => [
                        'id' => $issue->volume->id,
                        'number' => $issue->volume->number,
                        'year' => $issue->volume->year,
                    ],
                ],

                'articles' => $issue->articles
                    ->map(fn (Article $article): array => $this->articleRow($article, $journal, $issue))
                    ->values()
                    ->all(),

                'meta' => [
                    'title' => "Vol {$issue->volume->number}, No {$issue->number} — {$journal->title}",
                    'description' => 'The running order of the issue.',
                ],
            ],
        ));
    }

    public function store(Request $request, Journal $journal): RedirectResponse
    {
        $this->authorize('manageIssues', $journal);

        abort_unless($journal->usesIssues(), 404);

        $data = $request->validate([
            'volume_id' => [
                'required',
                Rule::exists('volumes', 'id')->where('journal_id', $journal->id),
            ],
            'number' => ['required', 'integer', 'min:1', 'max:999'],
            'season' => ['nullable', 'string', 'max:255'],
        ]);

        $duplicate = Issue::query()
            ->where('volume_id', $data['volume_id'])
            ->where('number', $data['number'])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'number' => "This volume already has an issue {$data['number']}.",
            ]);
        }

        Issue::create([
            'volume_id' => $data['volume_id'],
            'number' => $data['number'],
            'season' => $data['season'] ?? null,
            'status' => IssueStatus::Draft,
        ]);

        return back()->with('success', "Issue {$data['number']} created.");
    }

    public function update(Request $request, Issue $issue): RedirectResponse
    {
        // IssuePolicy::update is FALSE on a published issue. Its number and season are part
        // of every citation already made to it.
        $this->authorize('update', $issue);

        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:999'],
            'season' => ['nullable', 'string', 'max:255'],
        ]);

        $duplicate = Issue::query()
            ->where('volume_id', $issue->volume_id)
            ->where('number', $data['number'])
            ->whereKeyNot($issue->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'number' => "This volume already has an issue {$data['number']}.",
            ]);
        }

        $issue->update($data);

        return back()->with('success', 'Issue updated.');
    }

    /**
     * The running order. Reordering an issue changes which article a sequence-derived DOI
     * suffix refers to, so it is FORBIDDEN once the issue is published — by IssuePolicy,
     * which is asked here rather than reimplemented.
     */
    public function reorder(Request $request, Issue $issue): RedirectResponse
    {
        // Refuses a published issue outright: a 403, not a silent no-op.
        $this->authorize('manageArticles', $issue);

        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => [
                'integer',
                Rule::exists('articles', 'id')->where('issue_id', $issue->id),
            ],
        ]);

        $articles = Article::query()
            ->whereIn('id', $data['order'])
            ->where('issue_id', $issue->id)
            ->get()
            ->keyBy('id');

        // An individual article can be published inside a still-draft issue. Its sequence is
        // frozen (ArticleObserver would throw), so moving it is refused HERE, with a reason,
        // rather than as a 500 halfway through the loop.
        $frozen = [];

        foreach (array_values($data['order']) as $index => $id) {
            $article = $articles->get($id);

            if ($article !== null && $article->isFrozen() && $article->sequence !== $index + 1) {
                $frozen[] = str($article->title)->limit(40)->toString();
            }
        }

        if ($frozen !== []) {
            throw ValidationException::withMessages([
                'order' => array_map(
                    fn (string $title): string => "\"{$title}\" is published. Its position is frozen — "
                        .'the DOI suffix is derived from it.',
                    $frozen,
                ),
            ]);
        }

        DB::transaction(function () use ($data, $articles): void {
            foreach (array_values($data['order']) as $index => $id) {
                $article = $articles->get($id);

                if ($article === null || $article->sequence === $index + 1) {
                    continue;
                }

                $article->update(['sequence' => $index + 1]);
            }
        });

        return back()->with('success', 'Running order saved.');
    }

    /** @return array<string, mixed> */
    private function articleRow(Article $article, Journal $journal, Issue $issue): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'sequence' => $article->sequence,
            'status' => $article->status->value,
            'statusLabel' => $article->status->label(),
            'isPublished' => $article->isPublished(),
            'isFrozen' => $article->isFrozen(),
            'section' => $article->section?->name,
            'pages' => $article->pageRange(),
            'hasPdf' => $article->pdf !== null,
            'authors' => $article->hasCorporateAuthor()
                ? [$article->corporate_author]
                : $article->authors->map(fn ($a): string => $a->fullName())->values()->all(),

            // What the DOI WOULD be. Shown before publication, on purpose: an editor should
            // be able to read the identifier they are about to make permanent. It is only
            // frozen once the article is published.
            'doiSuffix' => $article->doi_suffix ?? $this->derivedSuffix($article, $journal, $issue),
            'doi' => $article->doi(),
            'suffixFrozen' => $article->doi_suffix !== null && $article->isFrozen(),
        ];
    }

    /**
     * The suffix the ONE generator would mint. NULL when it cannot — an article with no
     * position in the issue has no derivable identifier, and inventing a display value here
     * would be a second construction site for DOIs.
     */
    private function derivedSuffix(Article $article, Journal $journal, Issue $issue): ?string
    {
        try {
            return app(DoiSuffixGenerator::class)->generate(
                (clone $article)
                    ->setRelation('journal', $journal)
                    ->setRelation('issue', $issue)
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
