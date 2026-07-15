<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\JournalResource;
use App\Models\Article;
use App\Models\HomeSection;
use App\Models\Journal;
use App\Models\JournalMetric;
use App\Models\NewsItem;
use App\Models\ResearchTopic;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Home', [
            /**
             * The homepage's editorial copy, from the CMS.
             *
             * Everything in here used to be a literal in Home.tsx: the eyebrows, headings
             * and blurbs of every band, the four author-path cards, and the five
             * "how it works" steps — which rendered four invented medians ("About 20
             * minutes", "Median 4 days", "Median 38 days", "Median 9 days") as though they
             * were this platform's measured performance. They were not computed from
             * anything, and they contradicted the "Median 51 days" in the navbar.
             *
             * Keyed by section key so the page can ask for the one it is rendering.
             */
            'sections' => HomeSection::query()
                ->where('is_visible', true)
                ->with(['items', 'media'])
                ->orderBy('sequence')
                ->get()
                ->mapWithKeys(fn (HomeSection $s) => [$s->key => [
                    'eyebrow' => $s->eyebrow,
                    'heading' => $s->heading,
                    'blurb' => $s->blurb,
                    'image' => $s->media?->only(['url', 'alt', 'caption', 'credit']),
                    'items' => $s->items->map(fn ($i) => $i->toPayload())->values(),
                ]]),

            'stats' => $this->stats(),

            'featuredJournals' => JournalResource::collection(
                Journal::query()
                    ->where('is_active', true)
                    ->with(['field', 'metric', 'coverMedia'])
                    ->orderByDesc(
                        // Featured = most active. The frontend used to take JOURNALS.slice(0,3),
                        // i.e. whatever happened to be first in a hand-written array.
                        JournalMetric::select('article_count')
                            ->whereColumn('journal_metrics.journal_id', 'journals.id')
                            ->limit(1)
                    )
                    ->limit(3)
                    ->get()
            ),

            'latestArticles' => ArticleResource::collection(
                Article::query()
                    ->published()
                    ->with(['journal', 'authors', 'section'])
                    ->orderByDesc('published_at')
                    ->limit(4)
                    ->get()
            ),

            // withCount, not a per-row ->count(). The previous version read a non-existent
            // `articles_count` column (so every topic reported 0 articles) and issued one
            // extra query per topic for the editors.
            'researchTopics' => ResearchTopic::query()
                ->where('is_open', true)
                ->with('media')
                ->withCount('editors')
                ->orderBy('deadline')
                ->limit(3)
                ->get()
                ->map(fn (ResearchTopic $t) => [
                    'slug' => $t->slug,
                    'title' => $t->title,
                    'description' => $t->description,
                    'deadline' => $t->deadline?->toDateString(),

                    // The deadline is the single most important fact on a call for papers.
                    // Same shape the /topics index sends, so the card renders the same
                    // status pill in both places rather than guessing in one of them.
                    'isOpen' => (bool) $t->is_open && ($t->deadline === null || $t->deadline->isFuture()),
                    'hasClosed' => $t->deadline !== null && $t->deadline->isPast(),
                    // A research topic is a call for papers, not a container of articles —
                    // there is no articles relation to count. Report the editors, which is
                    // real, and let the card omit what we cannot honestly state.
                    'editors' => $t->editors_count,
                    // Every one of these cards used to link to /journals. They now go to
                    // the call for papers itself.
                    'url' => route('topics.show', $t->slug),
                    'image' => $t->media?->only(['url', 'alt', 'caption', 'credit']),
                    'photo' => $t->photo_key,
                ]),

            'news' => NewsItem::query()
                ->published()
                ->with('media')
                ->orderByDesc('published_at')
                ->limit(6)
                ->get()
                ->map(fn (NewsItem $n) => [
                    'slug' => $n->slug,
                    'title' => $n->title,
                    'category' => $n->category,
                    'date' => $n->published_at?->toDateString(),
                    'excerpt' => $n->excerpt,
                    // Six of these rendered with a "Read the story →" affordance and
                    // href="#". They now go to the story.
                    'url' => route('news.show', $n->slug),
                    'image' => $n->media?->only(['url', 'alt', 'caption', 'credit']),
                    'photo' => $n->photo_key,
                ]),

            'meta' => [
                'title' => config('app.name').' — Open-access publishing & journal management',
                'description' => 'An open-access publishing platform and end-to-end journal '
                    .'management system for authors, reviewers and editors.',
            ],
        ]);
    }

    /**
     * REAL aggregates.
     *
     * The prototype hardcoded "3.9M researchers on the platform", "16M citations" and
     * "5.3B article views". Those are Frontiers' numbers, not LCC's. Publishing them on
     * a live LCC site would be a straightforward false claim, and the sort a competitor
     * or a journalist checks first. These count what we actually have; when that is
     * seven articles, the honest thing is for the page to say seven.
     *
     * @return list<array{label: string, value: float, suffix: string, decimals: int}>
     */
    private function stats(): array
    {
        return [
            [
                'label' => 'Peer-reviewed articles published',
                'value' => (float) Article::published()->count(),
                'suffix' => '',
                'decimals' => 0,
            ],
            [
                'label' => 'Article views & downloads',
                'value' => (float) Article::published()->sum('views_count'),
                'suffix' => '',
                'decimals' => 0,
            ],
            [
                'label' => 'Open-access journals',
                'value' => (float) Journal::where('is_active', true)->where('open_access', true)->count(),
                'suffix' => '',
                'decimals' => 0,
            ],
        ];
    }
}
