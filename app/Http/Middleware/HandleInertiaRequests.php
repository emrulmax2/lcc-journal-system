<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Content\SiteContent;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->fullName(),
                    'email' => $request->user()->email,
                ] : null,
            ],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),

                /**
                 * The submission receipt: { reference, journal, title, medianDaysToDecision }.
                 *
                 * Submit.tsx's success screen prints ONLY what is in here. The prototype
                 * hardcoded "MRDN-2026-0451" and "51 days" — a manuscript ID that exists in
                 * no database is worse than none at all, because the author quotes it in an
                 * email and the editorial office has never heard of it. Without this flash
                 * the screen falls back to a generic confirmation, which is honest.
                 */
                'submission' => fn () => $request->session()->get('submission'),

                /**
                 * EVERY pre-flight failure from the publish gate, flattened into one list.
                 *
                 * Inertia's own `errors` prop keeps only the FIRST message per key, and the
                 * publish actions deliberately return several under one — 'pages' can hold
                 * three overlapping ranges, 'articles' one line per article in an issue.
                 * Showing an editor the first, letting them fix it, and then showing the
                 * second is exactly the one-at-a-time drip the actions exist to prevent, so
                 * the complete list travels here and Admin/* renders all of it.
                 */
                'publishErrors' => fn () => $request->session()->get('publishErrors'),
            ],

            /**
             * The server's "now", as an ISO string.
             *
             * The Dashboard previously computed overdue reviews against a hardcoded
             * literal date ('2026-07-13'). Under SSR, deriving "today" separately on the
             * server and the client also guarantees a hydration mismatch whenever a
             * render straddles midnight or a timezone boundary. One authoritative value,
             * sent from the server, is the only way both renders agree.
             */
            'now' => now()->toIso8601String(),

            /**
             * THE CMS LAYER — brand, navigation, footer. Shared into every page.
             *
             * This is what makes the chrome editable instead of hardcoded. Before it, the
             * navbar imported a fixture file and rendered six fictional journals with
             * fabricated impact factors (7.3, 6.1, 5.8…) on EVERY page of the site, the
             * footer told readers the publisher was fictional, and nine footer links went
             * to "#" or the homepage.
             *
             * Shape:
             *   site.settings  key => value (media settings resolve to a media object)
             *   site.menus     key => { name, location, items: [{ label, url, description, children }] }
             *
             * Cached in SiteContent and flushed by the models on save, so an editor's
             * change is live immediately rather than at the next deploy.
             */
            'site' => fn () => app(SiteContent::class)->shared(),
        ]);
    }
}
