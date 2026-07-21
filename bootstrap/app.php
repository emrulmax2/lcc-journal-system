<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackArticleView;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',

        /*
         * routes/discovery.php IS LOADED FROM web.php, NOT FROM A `then:` CALLBACK HERE.
         *
         * `then` runs AFTER the web routes are registered, and web.php ends with the
         * `/{page}` CMS catch-all. Laravel matches in registration order, so a discovery
         * route registered here is registered LAST — and /oai was matched by /{page} first,
         * looked up as a CMS page, and 404'd. Every harvester endpoint was dead, and the
         * only thing that noticed was OaiPmhTest.
         *
         * web.php requires discovery.php ABOVE the catch-all, which is what its own comment
         * has always demanded of any new route.
         */
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            // BEFORE HandleInertiaRequests, so a deactivated account is turned away rather
            // than having its identity shared to every page as `auth.user`.
            EnsureAccountIsActive::class,

            // BEFORE HandleInertiaRequests too, so the locale is set before the shared
            // `locale`/`translations` props are built for this request.
            SetLocale::class,

            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,

            // Counts a view of an article landing page, bot-filtered. An unfiltered hit
            // count on an academic page is mostly crawlers, and publishing that number as
            // "views" next to the article would be a misrepresentation, not a rounding error.
            TrackArticleView::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Render 404 / 403 / 500 through the Inertia app, so an error page looks like the
         * site rather than like a crash. Without this, the deliberate 404 we return for an
         * unpublished article — a security decision, not an accident — showed Laravel's
         * default stack-trace page, and NotFound.tsx was unreachable.
         *
         * The status code is preserved. An error page that returns HTTP 200 is worse than
         * no error page: crawlers index it as real content, and a "soft 404" on a DOI
         * landing page is exactly the failure this whole system exists to avoid.
         */
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($request->expectsJson() || ! in_array($response->getStatusCode(), [403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            // The machine-readable surfaces must NEVER be handed an HTML error page.
            // A harvester asking /oai for XML and receiving a styled React 404 cannot
            // parse it, cannot report why, and — worse — the HTML page masks the real
            // exception from us too. They get their own formats, or nothing.
            if ($request->is('oai', 'sitemap.xml') || str_ends_with($request->path(), '.pdf')) {
                return $response;
            }

            return Inertia::render('NotFound', [
                'status' => $response->getStatusCode(),
                'meta' => ['title' => 'Page not found'],
            ])
                ->toResponse($request)
                ->setStatusCode($response->getStatusCode());
        });
    })->create();
