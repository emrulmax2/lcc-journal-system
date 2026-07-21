<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\ArticleFileType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Services\Citations\CitationFormatter;
use App\Services\Content\MarkdownRenderer;
use App\Support\CitationMeta;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArticleController extends Controller
{
    /**
     * Filtering happens HERE, not in the browser.
     *
     * The React page used to useMemo over the entire article array. That works for six
     * fixture rows and collapses the moment a real archive exists — it ships every
     * article ever published to every visitor in order to show them ten.
     */
    public function index(Request $request): Response
    {
        $articles = Article::query()
            ->published()
            ->with(['journal', 'authors', 'section'])
            ->search($request->string('q')->toString())
            ->when($request->filled('journal') && $request->string('journal')->toString() !== 'all',
                fn ($q) => $q->whereHas('journal', fn ($j) => $j->where('slug', $request->string('journal'))))
            ->when($request->filled('type') && $request->string('type')->toString() !== 'all',
                fn ($q) => $q->whereHas('section', fn ($s) => $s->where('name', $request->string('type'))))
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Articles', [
            'articles' => ArticleResource::collection($articles),
            'journals' => Journal::query()
                ->where('is_active', true)
                ->orderBy('title')
                ->get(['slug', 'title'])
                ->map(fn (Journal $j) => ['slug' => $j->slug, 'title' => $j->title]),
            'types' => $this->articleTypes(),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'journal' => $request->string('journal', 'all')->toString(),
                'type' => $request->string('type', 'all')->toString(),
            ],
            'meta' => [
                'title' => 'Articles — '.config('app.name'),
                'description' => 'Browse peer-reviewed, open-access research.',
            ],
        ]);
    }

    /**
     * The DOI landing page. Everything about the DOI programme depends on this method
     * returning full, machine-readable HTML to a client that runs no JavaScript.
     */
    public function show(Request $request, Article $article): Response
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
            ->with(['journal', 'authors', 'section'])
            ->orderByDesc('published_at')
            ->limit(2)
            ->get();

        return Inertia::render('ArticleDetail', [
            'article' => array_merge(
                (new ArticleResource($article))->resolve($request),
                [
                    'body' => $article->body,
                    'pageRange' => $article->pageRange(),
                    'volume' => $article->issue?->volume?->number,
                    'issue' => $article->issue?->number,
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
    public function html(Request $request, Article $article, MarkdownRenderer $markdown): View
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
            'bodyHtml' => $markdown->toHtml($article->body),
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
