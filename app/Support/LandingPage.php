<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Where a person belongs when they sign in.
 *
 * There are TWO dashboards, and which one is "the" dashboard depends entirely on who is
 * asking:
 *
 *   /dashboard  — the editorial office. An author's submissions, a reviewer's queue, an
 *                 editor's decisions. A site admin who publishes nothing and reviews
 *                 nothing sees an empty page with four zeroes on it.
 *   /admin      — the editorial admin. Journals, issues, publication, DOIs, people.
 *
 * Login used to send EVERYONE to /dashboard. For a site admin that is the wrong room: they
 * landed on an empty editorial office, correctly concluded there was nothing there, and had
 * no link to the room they wanted. Hence this.
 *
 * The order is "most privileged first" — someone who is both an editor and an author lands
 * in the admin, because that is the work they signed in to do; the editorial office is one
 * click away in the nav either way. Nobody is ever sent somewhere they'd be 403'd:
 * Admin\DashboardController aborts when editorialJournals() is empty, which is exactly the
 * condition tested here.
 */
final class LandingPage
{
    public static function for(User $user): string
    {
        if ($user->is_site_admin) {
            return route('admin.dashboard');
        }

        // Asked of the POLICIES, per journal — the same question Admin\DashboardController
        // asks before it decides to 403. Keep the two in step or login sends people to a
        // page that refuses them.
        if (AdminChrome::editorialJournals($user)->isNotEmpty()) {
            return route('admin.dashboard');
        }

        return route('dashboard');
    }
}
