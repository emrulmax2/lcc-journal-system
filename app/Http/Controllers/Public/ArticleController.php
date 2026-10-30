<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\ArticleFileType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\IssueCardResource;
use App\Models\Article;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Services\Citations\CitationFormatter;
use App\Services\Content\ArticleBody;
use App\Support\CitationMeta;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArticleController extends Controller
{
    /**
     * The archive search, with a proper set of filters — all of them applied HERE, on the
     * server, and all of them in the URL so a filtered view can be shared or bookmarked:
     *
     *   q        free text (title, abstract, keywords, authors)
     *   volume   volume number                       ?volume=10
     *   issue    issue number WITHIN that volume     ?volume=10&issue=2
     *   type     article type (section name)         ?type=Research+Article
     *   journal  journal slug — only offered when there is more than one journal
     *   sort     newest (default) | oldest | title
     *
     * Every option in the sidebar carries a count computed with the OTHER filters applied
     * (a faceted search), so a reader can see before clicking that "Case Study" has 3
     * matches in this volume — and never clicks through to an empty page.
     */
    public function index(Request $request): Response
    {
        $f = [
            'q' => trim($request->string('q')->toString()),
            'journal' => $this->choice($request, 'journal'),
            'volume' => $request->integer('volume') ?: null,
            'issue' => $request->integer('issue') ?: null,
            'type' => $this->choice($request, 'type'),
            'sort' => in_array($request->string('sort')->toString(), ['oldest', 'title'], true)
                ? $request->string('sort')->toString()
                : 'newest',
        ];

        // An issue number only means something inside a volume.
        if ($f['volume'] === null) {
            $f['issue'] = null;
        }

        $articles = $this->filtered($f)
            ->with(['journal', 'authors', 'section', 'issue.volume', 'issue.coverMedia'])
            ->tap(fn (Builder $q) => match ($f['sort']) {
                'oldest' => $q->orderBy('published_at')->orderBy('sequence'),
                'title' => $q->orderBy('title'),
                // Articles in one issue share a publication date; within it, print order.
                default => $q->orderByDesc('published_at')->orderBy('sequence'),
            })
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        $journals = Journal::query()
            ->where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'slug', 'title']);
        $journal = $f['journal'] ? $journals->firstWhere('slug', $f['journal']) : null;

        // Facet counts. Issues (and so volumes) are counted ignoring the volume/issue
        // filter; types are counted ignoring the type filter.
        $issueCounts = $this->filtered($f, ['volume', 'issue'])
            ->whereNotNull('issue_id')
            ->toBase()
            ->selectRaw('issue_id, COUNT(*) AS n')
            ->groupBy('issue_id')
            ->pluck('n', 'issue_id');

        $issues = IssueCardResource::query($journal)->get()->map(fn (Issue $i) => [
            'volume' => $i->volume->number,
            'number' => $i->number,
            'season' => $i->season,
            'year' => $i->publication_date?->year ?? $i->volume->year,
            'label' => trim("{$i->label()} · {$i->season}", ' ·'),
            'count' => (int) ($issueCounts[$i->id] ?? 0),
        ])->values();

        $volumes = $issues
            ->groupBy('volume')
            ->map(function ($inVolume, $number) {
                $years = $inVolume->pluck('year')->filter()->unique()->sort()->values();

                return [
                    'number' => (int) $number,
                    'years' => $years->count() > 1 ? $years->first().'–'.$years->last() : (string) $years->first(),
                    'count' => $inVolume->sum('count'),
                ];
            })
            ->sortByDesc('number')
            ->values();

        $sectionCounts = $this->filtered($f, ['type'])
            ->toBase()
            ->selectRaw('journal_section_id, COUNT(*) AS n')
            ->groupBy('journal_section_id')
            ->pluck('n', 'journal_section_id');
        $sectionNames = JournalSection::whereIn('id', $sectionCounts->keys())->pluck('name', 'id');
        $typeCounts = [];
        foreach ($sectionCounts as $id => $n) {
            if (isset($sectionNames[$id])) {
                $typeCounts[$sectionNames[$id]] = ($typeCounts[$sectionNames[$id]] ?? 0) + (int) $n;
            }
        }

        return Inertia::render('Articles', [
            'articles' => ArticleResource::collection($articles),
            'journals' => $journals->map(fn (Journal $j) => ['slug' => $j->slug, 'title' => $j->title])->values(),
            'volumes' => $volumes,
            'issues' => $issues,
            'types' => collect($this->articleTypes())
                ->map(fn (string $name) => ['name' => $name, 'count' => $typeCounts[$name] ?? 0])
                ->values(),
            'filters' => [
                'q' => $f['q'],
                'journal' => $f['journal'] ?? 'all',
                'volume' => $f['volume'],
                'issue' => $f['issue'],
                'type' => $f['type'] ?? 'all',
                'sort' => $f['sort'],
            ],
            'meta' => [
                'title' => 'Articles — '.config('app.name'),
                'description' => 'Browse peer-reviewed, open-access research.',
            ],
        ]);
    }

    /**
     * Published articles matching the filters, minus the ones named in $except — so a facet
     * can be counted as if its own filter were not set.
     *
     * @param  array<string, mixed>  $f
     * @param  list<string>  $except
     * @return Builder<Article>
     */
    private function filtered(array $f, array $except = []): Builder
    {
        $use = fn (string $key): bool => filled($f[$key]) && ! in_array($key, $except, true);

        return Article::query()
            ->published()
            ->search($f['q'])
            ->when($use('journal'), fn (Builder $q) => $q->whereHas('journal', fn (Builder $j) => $j->where('slug', $f['journal'])))
            ->when($use('volume'), fn (Builder $q) => $q->whereHas('issue', function (Builder $i) use ($f, $use) {
                $i->published()->whereHas('volume', fn (Builder $v) => $v->where('number', $f['volume']));

                if ($use('issue')) {
                    $i->where('number', $f['issue']);
                }
            }))
            ->when($use('type'), fn (Builder $q) => $q->whereHas('section', fn (Builder $s) => $s->where('name', $f['type'])));
    }

    /** A select/radio value, where "all" (or nothing) means no filter. */
    private function choice(Request $request, string $key): ?string
    {
        $value = trim($request->string($key)->toString());

        return $value === '' || $value === 'all' ? null : $value;
    }

    /**
     * The DOI landing page. Everything about the DOI programme depends on this method
     * returning full, machine-readable HTML to a client that runs no JavaScript.
     */
    public function show(Request $request, Article $article, ArticleBody $articleBody): Response
    {
        $article->load(['journal', 'authors', 'section', 'issue.volume', 'pdf', 'references', 'heroMedia']);

        // A draft is not a public object. It 404s for guests — NOT 403, which would
        // confirm the article exists and leak an embargoed title. An editor with rights
        // on this journal gets to preview it, but the preview is marked noindex so a
        // logged-in crawler can never leak it into an index either.
        $isPreview = ! $article->isPublished();

        if ($isPreview) {
            abort_unless(
                $request->user()?->can('manageArticles', $article->journal) ?? false,
                404
            );
        }

        $related = Article::query()
            ->published()
            ->where('journal_id', $article->journal_id)
            ->whereKeyNot($article->id)
            ->with(['journal', 'authors', 'section', 'issue.volume', 'issue.coverMedia'])
            ->orderByDesc('published_at')
            ->limit(2)
            ->get();

        // The full text as HTML, rendered HERE — so it is in the server-rendered page that
        // Scholar reads — with an id on every section heading for the "On this page" list.
        // It used to be sent as raw Markdown and split on blank lines in the browser, which
        // printed "## Introduction" as text and could not show a table or a figure at all.
        $body = $articleBody->render($article->body);

        return Inertia::render('ArticleDetail', [
            'article' => array_merge(
                (new ArticleResource($article))->resolve($request),
                [
                    'bodyHtml' => $body['html'],
                    'toc' => $body['toc'],
                    'pageRange' => $article->pageRange(),
                    'volume' => $article->issue?->volume?->number,
                    'issue' => $article->issue?->number,
                    // "Vol 5, No 1" links to that issue's table of contents — once the issue
                    // itself is public. A link to an unpublished issue would be a 404.
                    'issueUrl' => $article->issue?->isPublished() ? $article->issue->url() : null,
                    'license' => $article->journal->license,
                    'licenseHolder' => $article->journal->license_holder,

                    /**
                     * The article's OWN figure, uploaded with the manuscript.
                     *
                     * NULL means the page renders no figure at all. It used to render a
                     * stock Unsplash photo captioned "Figure 1. Representative imagery from
                     * the study site" — on a published paper, that is a fabrication, not a
                     * placeholder. A blank is honest; a stranger's laboratory is not.
                     */
                    'heroImage' => $article->heroMedia?->only(['url', 'alt', 'caption', 'credit']),

                    // The Open Access badge was rendered UNCONDITIONALLY on this page. It is
                    // a claim about the licence, and it must come from the journal.
                    'journalOpenAccess' => (bool) $article->journal->open_access,
                    'hasPdf' => $article->hasPdf(),
                    'pdfUrl' => $article->hasPdf() ? $article->pdfUrl() : null,
                    'hasHtmlFullText' => $article->hasHtmlFullText(),
                    'htmlUrl' => $article->hasHtmlFullText() ? $article->htmlUrl() : null,
                    'isPreview' => $isPreview,
                    'authorDetails' => $article->authors->map(fn ($a) => [
                        'name' => $a->fullName(),
                        'affiliation' => $a->affiliation,
                        'orcid' => $a->orcid,
                        'orcidUrl' => $a->orcidUrl(),
                        'isCorresponding' => $a->is_corresponding,
                    ])->values(),
                    'corporateAuthor' => $article->corporate_author,
                    'references' => $article->references->map(fn ($r) => [
                        'ordinal' => $r->ordinal,
                        'text' => $r->raw_text,
                        'doi' => $r->doi,
                    ])->values(),
                ],
            ),
            'citations' => app(CitationFormatter::class)->all($article),
            'related' => ArticleResource::collection($related),
            'meta' => [
                'title' => $article->title.' — '.$article->journal->title,
                'description' => str($article->abstract ?? '')->squish()->limit(160)->toString(),
            ],
        ])->withViewData([
            // THE POINT OF THIS ENTIRE PHASE. Rendered by Blade, in PHP, so the tags exist
            // in the raw HTML even if the Node SSR process is dead. See app.blade.php.
            'citationMeta' => CitationMeta::for($article),
            'canonical' => $article->landingUrl(),
            'indexable' => ! $isPreview,
        ]);
    }

    /**
     * The stable PDF route. citation_pdf_url points here, and Google Scholar WILL fetch
     * it — so the URL must never move, and it must never 404 while being advertised.
     *
     * Files live on a private disk and are streamed, so that access can be counted and,
     * later, embargoed. A public symlink would make both impossible.
     */
    public function pdf(Request $request, Article $article): StreamedResponse
    {
        abort_unless(
            $article->isPublished() || ($request->user()?->can('manageArticles', $article->journal) ?? false),
            404
        );

        $file = $article->files()->where('type', ArticleFileType::Pdf)->first();

        abort_if($file === null, 404);
        abort_unless(Storage::disk('private')->exists($file->path), 404);

        $file->increment('downloads_count');

        return Storage::disk('private')->response(
            $file->path,
            $this->downloadFilename($article),
            [
                'Content-Type' => 'application/pdf',
                // inline: Scholar and humans both expect the PDF to open, not to be a
                // forced download that a crawler cannot follow.
                'Content-Disposition' => 'inline; filename="'.$this->downloadFilename($article).'"',
            ],
        );
    }

    /**
     * The crawlable HTML full text — server-rendered by Blade, NOT React.
     *
     * This is the point: Google Scholar reads the full body from this page without running a
     * line of JavaScript, and it keeps working if the Node SSR process dies. The body is
     * rendered through MarkdownRenderer, which ESCAPES raw HTML — an editor cannot inject a
     * <script> into a public page, and there is no HTML to sanitise after the fact.
     *
     * The same citation_* meta tags the landing page carries are emitted here too, and the
     * canonical link points back at the DOI landing page so this is not treated as a duplicate.
     */
    public function html(Request $request, Article $article, ArticleBody $articleBody): View
    {
        $article->load(['journal', 'authors', 'section', 'issue.volume', 'references']);

        $isPreview = ! $article->isPublished();

        if ($isPreview) {
            abort_unless(
                $request->user()?->can('manageArticles', $article->journal) ?? false,
                404
            );
        }

        // No body, no full-text page. Advertising citation_fulltext_html_url is gated on the
        // same condition, so this only 404s on a URL nobody was told to fetch.
        abort_unless($article->hasHtmlFullText(), 404);

        return view('articles.fulltext', [
            'article' => $article,
            'bodyHtml' => $articleBody->render($article->body)['html'],
            'citationMeta' => CitationMeta::for($article),
            'canonical' => $article->landingUrl(),
            'indexable' => ! $isPreview,
        ]);
    }

    private function downloadFilename(Article $article): string
    {
        return ($article->doi_suffix ?? $article->slug).'.pdf';
    }

    /** @return list<string> */
    private function articleTypes(): array
    {
        return JournalSection::query()
            ->where('is_active', true)
            ->distinct()
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();
    }
}
