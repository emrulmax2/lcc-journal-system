<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\LandingPage;
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

        /*
         * is_active IS CHECKED HERE, AFTER THE PASSWORD, AND THAT ORDER IS THE POINT.
         *
         * Auth::attempt() does not know about the column, so deactivating someone used to
         * do NOTHING to their ability to sign in — the People screen showed "Inactive"
         * beside a person who could still log in and act. The flag has to be enforced at
         * the door or it is decoration.
         *
         * Not folded into attempt() as an extra credential (['is_active' => true]) on
         * purpose: that returns false, which the branch above reports as "credentials do
         * not match" — a lie that sends a former editor to reset a password that is fine.
         *
         * Telling them the truth here is NOT the enumeration leak the branch above guards
         * against, because we only reach this line for someone who has just PROVED the
         * password. They know the account exists; they own it.
         */
        if (! $request->user()->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Contact the editorial office.',
            ]);
        }

        // Session fixation: the pre-login session id must not survive the privilege change.
        $request->session()->regenerate();

        // NOT route('dashboard') for everybody. A site admin sent to the editorial office
        // lands on an empty page with no way through to the admin — see LandingPage.
        return redirect()->intended(LandingPage::for($request->user()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
