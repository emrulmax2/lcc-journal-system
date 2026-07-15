<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
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
         * The pivot is queried DIRECTLY rather than through $user->roles(), because with
         * teams enabled that relation is scoped to the CURRENT team id — which in a request
         * that names no journal is NULL, so it would answer "no roles" for everybody.
         */
        Gate::define('manage-site-content', function (User $user): bool {
            if ($user->is_site_admin) {
                return true;
            }

            $tables = config('permission.table_names');
            $columns = config('permission.column_names');

            return DB::table($tables['model_has_roles'])
                ->join($tables['roles'], "{$tables['roles']}.id", '=', "{$tables['model_has_roles']}.role_id")
                ->where("{$tables['model_has_roles']}.{$columns['model_morph_key']}", $user->getKey())
                ->where("{$tables['model_has_roles']}.model_type", $user->getMorphClass())
                ->where("{$tables['roles']}.name", 'publisher-admin')
                ->exists();
        });

        // citation_pdf_url and citation_abstract_html_url must be absolute and must match
        // the canonical scheme. Behind cPanel's proxy the app can otherwise generate
        // http:// URLs on an https:// site, and Scholar treats the mismatch as a
        // different page from the one the DOI resolves to.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
