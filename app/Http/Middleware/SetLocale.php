<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request's locale and sets it, so every downstream string — Blade, Laravel
 * validation, the translations shared to React — speaks the same language.
 *
 * Precedence, most specific first:
 *   1. an explicit ?lang= on this request (a shared link in a given language wins);
 *   2. the reader's stored choice (session), set by the switcher;
 *   3. a signed-in user's saved preference;
 *   4. the browser's Accept-Language, if we speak one of its languages;
 *   5. the app default.
 *
 * Runs BEFORE HandleInertiaRequests, so the shared `locale`/`translations` props reflect the
 * locale actually in force for this request.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        app()->setLocale($locale);

        // Persist an explicit choice so the next request keeps it without the query string.
        if (Locales::isSupported($request->query('lang'))) {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $candidates = [
            $request->query('lang'),
            $request->session()->get('locale'),
            $request->user()?->preferred_locale,
            $this->fromAcceptLanguage($request),
        ];

        foreach ($candidates as $candidate) {
            if (Locales::isSupported($candidate)) {
                return $candidate;
            }
        }

        return Locales::default();
    }

    /** The first Accept-Language entry whose primary tag we actually support. */
    private function fromAcceptLanguage(Request $request): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $primary = strtolower(substr((string) $language, 0, 2));

            if (Locales::isSupported($primary)) {
                return $primary;
            }
        }

        return null;
    }
}
