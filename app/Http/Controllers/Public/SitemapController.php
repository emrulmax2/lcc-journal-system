<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
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

        $xml = view('sitemap', [
            'articles' => $articles,
            'staticUrls' => [
                route('home'),
                route('journals.index'),
                route('articles.index'),
            ],
        ])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
