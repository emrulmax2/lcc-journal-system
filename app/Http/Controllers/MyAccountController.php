<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MY ACCOUNT — the signed-in person editing THEMSELVES.
 *
 * Deliberately a different controller from Admin\AccountController, which is named for the
 * same noun and is not the same job:
 *
 *   Admin\AccountController — an administrator managing OTHER PEOPLE. Behind `manage-users`.
 *                             Can set is_active, is_site_admin and roles on every journal.
 *   MyAccountController (here) — anyone signed in, editing their own name, email, affiliation,
 *                             ORCID and password. Behind `auth` and NOTHING ELSE, because
 *                             every reviewer and author needs it and none of them are admins.
 *
 * THE PRIVILEGE FIELDS ARE NOT HERE, and their absence is the design. `is_active`,
 * `is_site_admin` and role assignments are not in the validated set, are not read off the
 * request, and there is no shape of this controller that could write them — so this route
 * cannot become a self-service privilege escalation by someone later adding a field to the
 * form. Changing what you may do stays with Admin\AccountController and its gates.
 *
 * `$request->user()` is the subject of every method. There is no {account} parameter to
 * authorise, because there is no way to name anyone else.
 */
final class MyAccountController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Account', [
            'account' => [
                'givenName' => $user->given_name,
                'familyName' => $user->family_name,
                /** The fallback display name, for accounts predating the given/family split. */
                'name' => $user->name,
                'email' => $user->email,
                'affiliation' => $user->affiliation,
                'orcid' => $user->orcid,
            ],

            'meta' => [
                'title' => 'My account — '.config('app.name'),
                'description' => 'Your name, contact details and password.',
            ],
        ])->withViewData([
            /*
             * NOINDEX, through the same `indexable` view flag the article previews use — see
             * app.blade.php. This page is behind `auth` so a crawler cannot reach it anyway,
             * but the tag costs nothing and it is the inverse of the public pages' contract:
             * everything under /articles MUST be indexable, and this MUST NOT be.
             */
            'indexable' => false,
        ]);
    }

    /**
     * Name, email, affiliation, ORCID. Not the password — see updatePassword.
     *
     * Two forms rather than one because they fail differently: a wrong current password must
     * not discard a corrected affiliation, and "saved" for a profile edit means something
     * different from "saved" for a credential change.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            // Crossref needs given and family separately — see User::fullName(). Required
            // here for the same reason the admin editor requires them: a deposit built from
            // half a name is a metadata correction nobody catches until it is public.
            'given_name' => ['required', 'string', 'max:120'],
            'family_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'affiliation' => ['nullable', 'string', 'max:255'],
            'orcid' => ['nullable', 'string', 'regex:/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/'],
        ], [
            'orcid.regex' => 'An ORCID looks like 0000-0002-1825-0097.',
        ]);

        $user->fill([
            'given_name' => $data['given_name'],
            'family_name' => $data['family_name'],
            // Mirrors Admin\AccountController: `name` is the display fallback and must not be
            // left pointing at the old spelling once the real fields have moved on.
            'name' => trim("{$data['given_name']} {$data['family_name']}"),
            'email' => $data['email'],
            'affiliation' => $data['affiliation'] ?? null,
            // ?? for the absent key (a `nullable` field that validated empty is dropped from
            // the array entirely), then ?: to fold an empty string down to a real null.
            'orcid' => ($data['orcid'] ?? null) ?: null,
        ]);

        $user->save();

        return back()->with('success', 'Your details have been saved.');
    }

    /**
     * CURRENT PASSWORD IS REQUIRED, and that is not ceremony.
     *
     * Without it, an unattended signed-in browser is a permanent account takeover rather than
     * a session someone can end — the attacker sets a password the owner does not know and
     * the owner cannot get back in. `current_password` checks against the session guard's
     * user, so it cannot be pointed at anybody else's hash.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            // 12, matching Admin\AccountController. One minimum, in both places, or the
            // self-service form quietly becomes the weak way in.
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ], [
            'current_password.current_password' => 'That is not your current password.',
            'password.min' => 'Use at least 12 characters.',
            'password.confirmed' => 'The two new passwords do not match.',
        ]);

        $user = $request->user();

        // Cast to `hashed` on the model — assigning the plaintext is correct, and hashing it
        // here as well would double-hash and lock the account out silently.
        $user->password = $validated['password'];
        $user->save();

        /*
         * A new session id for the browser doing the changing. The credential that authorised
         * this session has just been replaced, so the identifier standing in for it should be
         * too — cheap, and it closes session fixation across the change.
         *
         * It does NOT sign other browsers out: that needs AuthenticateSession in the web
         * middleware stack, which this app does not use. Saying so here rather than shipping
         * a logoutOtherDevices() call that silently does nothing.
         */
        $request->session()->regenerate();

        return back()->with('success', 'Your password has been changed.');
    }
}
