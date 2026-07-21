<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Support\GlobalRoles;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Resources arrive at React as plain arrays rather than { data: [...] }. The page
        // components were written against flat arrays and there is no reason to make every
        // one of them reach through a wrapper.
        JsonResource::withoutWrapping();

        /**
         * The one global bypass in the system.
         *
         * Reads a column, not a Spatie role — see the migration adding `is_site_admin`.
         * Roles are per-journal (Spatie teams), and "site admin" is not a relationship to
         * a journal; it is a property of the person.
         *
         * Returning NULL (not false) when the check does not apply is essential: false
         * would DENY the ability outright and short-circuit every policy behind it, so a
         * single non-admin user would be unable to do anything at all.
         */
        Gate::before(fn (User $user) => $user->is_site_admin ? true : null);

        /**
         * CMS CONTENT IS SITE-WIDE. ROLES ARE PER-JOURNAL. THERE IS NO PER-JOURNAL ANSWER
         * TO "MAY YOU EDIT THE PRIVACY POLICY".
         *
         * So this is a Gate, not a policy method on Journal: there is no journal to scope
         * it to. The privacy policy, the footer, the navigation and the homepage belong to
         * the site, not to JCD&MS.
         *
         * It deliberately does NOT introduce a global `site-admin` Spatie role. Spatie's
         * teams feature puts journal_id in the PRIMARY KEY of model_has_roles, so a global
         * role would need either a sentinel journal_id or one assignment per journal (and
         * would then silently fail to apply to a journal created tomorrow). See the
         * migration adding users.is_site_admin.
         *
         * Who may edit site content:
         *   - a site admin (the column), or
         *   - anyone holding `publisher-admin` on ANY journal — a publisher-admin is the
         *     person who runs the publishing operation, and the operation is what the
         *     footer and the policy pages describe.
         *
         * The "on ANY journal" question cannot go through $user->roles() — see GlobalRoles
         * for why the pivot is read directly.
         */
        Gate::define('manage-site-content', fn (User $user): bool => $user->is_site_admin
            || GlobalRoles::holdsAnywhere($user, 'publisher-admin'));

        /**
         * ACCOUNTS ARE SITE-WIDE. A user is not "of" a journal — they exist, and then they
         * hold roles on journals. So creating, editing and deactivating one is a Gate with
         * no model, exactly like manage-site-content, and for the same reason.
         *
         * A publisher-admin is included: they run the publishing operation, and onboarding
         * an editor is that job. What they CANNOT do is the next two gates.
         */
        Gate::define('manage-users', fn (User $user): bool => $user->is_site_admin
            || GlobalRoles::holdsAnywhere($user, 'publisher-admin'));

        /**
         * SITE ADMIN ONLY, AND THE REASON IS PRIVILEGE ESCALATION.
         *
         * is_site_admin is read by Gate::before — the one global bypass in the system. If a
         * publisher-admin could set it, `manage-users` would silently be the highest
         * privilege in the app: grant yourself the column, and every policy answers true
         * forever. The two abilities MUST NOT collapse into one.
         */
        Gate::define('grant-site-admin', fn (User $user): bool => $user->is_site_admin);

        /**
         * SITE ADMIN ONLY. Editing a role's permissions rewrites what that role means on
         * EVERY journal at once — role definitions are team-agnostic (journal_id NULL) and
         * only assignments are scoped. A journal-editor of one journal must not be able to
         * redefine `journal-editor` for all of them.
         */
        Gate::define('manage-roles', fn (User $user): bool => $user->is_site_admin);

        // citation_pdf_url and citation_abstract_html_url must be absolute and must match
        // the canonical scheme. Behind cPanel's proxy the app can otherwise generate
        // http:// URLs on an https:// site, and Scholar treats the mismatch as a
        // different page from the one the DOI resolves to.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
