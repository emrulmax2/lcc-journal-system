<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Laravel's built-in session auth, and nothing more. No Breeze, no Jetstream: the editorial
 * office needs a login, not a scaffold with its own opinions about layout, profile pages and
 * password resets that would have to be undone to match the design system.
 *
 * The login page is a BLADE view, deliberately. Every Inertia page in this app is a public,
 * server-rendered reading surface; the login screen is neither, and giving it a React page
 * would put an auth form behind the SSR process for no benefit.
 */
final class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // One message for both a wrong password and an unknown address. Distinguishing
            // them turns the login form into an account-enumeration oracle — and here the
            // accounts are named reviewers and editors of a journal.
            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        // Session fixation: the pre-login session id must not survive the privilege change.
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
