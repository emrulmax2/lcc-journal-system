<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\IssueCardResource;
use App\Models\Article;
use App\Models\Issue;
use App\Models\Journal;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        // Only published articles. A draft in the sitemap invites a crawler to fetch a
        // URL that 404s, and repeated 404s from a sitemap are read as a quality signal
        // against the whole domain.
        $articles = Article::query()
            ->published()
            ->orderByDesc('published_at')
            ->get(['slug', 'updated_at', 'published_at']);

        // The journal landing pages — real, indexable URLs (aims & scope, ISSN, metrics) that
        // DOAJ and Scholar both look for and that were missing from the sitemap.
        $journalUrls = Journal::query()
            ->where('is_active', true)
            ->orderBy('title')
            ->pluck('slug')
            ->map(fn (string $slug): string => route('journals.show', $slug))
            ->all();

        // Every published issue's table of contents — the way the back catalogue is browsed.
        $issueUrls = IssueCardResource::query()
            ->get()
            ->map(fn (Issue $issue): string => $issue->url())
            ->all();

        $xml = view('sitemap', [
            'articles' => $articles,
            'staticUrls' => array_merge([
                route('home'),
                route('journals.index'),
                route('articles.index'),
            ], $journalUrls, $issueUrls),
        ])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
