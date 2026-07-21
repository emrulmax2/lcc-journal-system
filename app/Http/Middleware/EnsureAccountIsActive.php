<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deactivation must take effect NOW, not at next login.
 *
 * LoginController refuses a deactivated account at the door. That alone leaves a hole big
 * enough to drive an incident through: the person you just deactivated is, by definition,
 * usually already signed in. Their session keeps working — for up to two weeks, with
 * "remember me" — and the People screen cheerfully shows them as Inactive the whole time.
 * Deactivation is most often used the day someone leaves, which is precisely when "they
 * keep their access until they choose to log out" is the wrong answer.
 *
 * So the flag is re-read on every authenticated request. It is one indexed read on a row
 * the session already loaded.
 *
 * Runs on the `web` group rather than inside `auth`: a guest has no account to check, so
 * the null-guard below short-circuits, and putting it here means a route added tomorrow
 * behind `auth` is covered without anyone remembering to opt in.
 */
final class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // A 403 would strand them on a dead page still holding a logged-in session
            // cookie. Send them to the login form, which is where the honest message
            // about deactivation lives.
            return redirect()->route('login')->withErrors([
                'email' => 'This account has been deactivated. Contact the editorial office.',
            ]);
        }

        return $next($request);
    }
}
