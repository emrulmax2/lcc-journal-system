<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\Journal;
use App\Services\Content\MarkdownRenderer;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The journal landing page.
 *
 * There was no such page. Every journal card, every mega-menu entry and every article's
 * journal link went to `/articles?journal={slug}` — a filtered article list. So these
 * columns existed and were read by NOTHING:
 *
 *     aims_and_scope, issn_online, issn_print, principal_editor, contact_email,
 *     publisher, abbreviation, cover_path
 *
 * That is not merely untidy. **DOAJ will not accept a journal that has no public aims and
 * scope page**, and an ISSN that appears nowhere a human can see it is not much use to
 * the person trying to verify the journal is real. This page is a prerequisite for the
 * application, not a nice-to-have.
 */
class JournalShowController extends Controller
{
    public function __invoke(Journal $journal): Response
    {
        abort_unless($journal->is_active, 404);

        $journal->load(['field', 'metric', 'sections']);

        $articles = Article::query()
            ->published()
            ->where('journal_id', $journal->id)
            ->with(['journal', 'authors', 'section'])
            ->orderByDesc('published_at')
            ->limit(6)
            ->get();

        return Inertia::render('Journal', [
            'journal' => [
                'slug' => $journal->slug,
                'title' => $journal->title,
                'abbreviation' => $journal->abbreviation,
                'field' => $journal->field?->name,
                'description' => $journal->description,
                'aimsAndScopeHtml' => app(MarkdownRenderer::class)->toHtml($journal->aims_and_scope),

                'publisher' => $journal->publisher,
                'principalEditor' => $journal->principal_editor,
                'contactEmail' => $journal->contact_email,

                // NULL until the British Library issues one. The page says so plainly
                // rather than printing "0000-0000", which is what the prototype did — a
                // placeholder ISSN looks like a real one to everybody except a librarian.
                'issnOnline' => $journal->issn_online,
                'issnPrint' => $journal->issn_print,

                'openAccess' => (bool) $journal->open_access,
                'license' => $journal->license,
                'publicationModel' => $journal->publication_model->value,
                'sections' => $journal->sections->where('is_active', true)->pluck('name')->values(),

                'coverImage' => $journal->coverMedia?->only(['url', 'alt', 'caption', 'credit']),
                'photo' => $journal->photo_key,

                // Externally sourced (JCR / Scopus) vs computed from our own data — the
                // page must label which is which, because an author reading "acceptance
                // rate" is entitled to know whether we worked it out or someone else did.
                'metrics' => [
                    'impactFactor' => $journal->metric?->impact_factor,
                    'citeScore' => $journal->metric?->cite_score,
                    'externalAsOf' => $journal->metric?->external_updated_at?->toDateString(),
                    'acceptanceRate' => $journal->metric?->acceptance_rate,
                    'medianDaysToDecision' => $journal->metric?->median_days_to_decision,
                    'articleCount' => $journal->metric?->article_count ?? 0,
                    'editorCount' => $journal->metric?->editor_count ?? 0,
                    'computedAt' => $journal->metric?->computed_at?->toDateString(),
                ],
            ],

            'latestArticles' => ArticleResource::collection($articles),

            'meta' => [
                'title' => $journal->title,
                'description' => $journal->description
                    ?: app(MarkdownRenderer::class)->toText($journal->aims_and_scope),
            ],
        ])->withViewData(['canonical' => route('journals.show', $journal->slug)]);
    }
}
