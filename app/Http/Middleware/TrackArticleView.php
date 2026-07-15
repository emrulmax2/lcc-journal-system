<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Article;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts a view of an article landing page — BOT-FILTERED.
 *
 * An unfiltered hit count on an academic landing page is mostly crawlers. Publishing that
 * number as "views" would not be a rounding error, it would be a misrepresentation: the
 * figure appears next to the article, authors quote it, and it is one of the things a
 * journal is judged on.
 *
 * This is a floor, not COUNTER compliance. It removes the obvious robots and the obvious
 * double-counts. If these numbers are ever going to be used to make a claim about
 * readership, they need a real COUNTER implementation.
 */
class TrackArticleView
{
    /** The crawlers we most want reading these pages — and most want excluded from the count. */
    private const BOT_PATTERNS = [
        'bot', 'crawl', 'spider', 'slurp', 'scholar', 'facebookexternalhit',
        'ia_archiver', 'wget', 'curl', 'python-requests', 'headless', 'lighthouse',
        'crossref', 'doaj', 'harvester', 'oai', 'monitor', 'preview', 'healthcheck',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only a successful GET of a real, published article counts.
        $article = $request->route('article');

        if (! $article instanceof Article
            || ! $request->isMethod('GET')
            || $response->getStatusCode() !== 200
            || ! $article->isPublished()
            || $this->looksLikeABot($request)) {
            return $response;
        }

        $this->record($article, $request);

        return $response;
    }

    private function looksLikeABot(Request $request): bool
    {
        $agent = strtolower((string) $request->userAgent());

        if ($agent === '') {
            return true;   // no user agent at all is not a person
        }

        foreach (self::BOT_PATTERNS as $pattern) {
            if (str_contains($agent, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function record(Article $article, Request $request): void
    {
        // De-duplicate per visitor per article per day. Without this, a reader who reloads
        // the page ten times while reading it registers as ten readers.
        //
        // The key is HASHED — an IP address plus a browsing history is personal data, and
        // there is no reason for this system to be able to reconstruct who read what.
        $visitor = hash('sha256', implode('|', [
            $request->ip(),
            $request->userAgent(),
            config('app.key'),
            now()->toDateString(),
        ]));

        $cacheKey = "view:{$article->id}:{$visitor}";

        if (cache()->has($cacheKey)) {
            return;
        }

        cache()->put($cacheKey, true, now()->endOfDay());

        // Upsert the daily row and bump the denormalised counter. article_metric_daily is
        // the source of truth; articles.views_count is a roll-up for display.
        DB::table('article_metric_daily')->upsert(
            [[
                'article_id' => $article->id,
                'date' => now()->toDateString(),
                'views' => 1,
                'downloads' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['article_id', 'date'],
            ['views' => DB::raw('views + 1'), 'updated_at' => DB::raw('VALUES(updated_at)')],
        );

        $article->incrementQuietly('views_count');
    }
}
