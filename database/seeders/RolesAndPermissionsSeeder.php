<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles are PER JOURNAL (Spatie teams, team = journal). A person edits Journal A and
 * reviews for Journal B; a global role cannot express that.
 *
 * Roles themselves are created team-agnostic (journal_id = NULL) and are ASSIGNED with
 * a team context. That is Spatie's intended shape: one `journal-editor` role definition,
 * granted separately on each journal.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * journal.publish is the high-privilege gate. It makes URLs permanent and spends
     * money at Crossref, and there is no undo — so `production` deliberately does NOT
     * get it, even though production can otherwise edit every field of an article.
     */
    private const PERMISSIONS = [
        'journal.view',
        'journal.settings.manage',
        'journal.issue.manage',
        'journal.article.manage',
        'journal.publish',
        'journal.doi.deposit',
        'journal.users.manage',
        'submission.create',
        'submission.view.own',
        'submission.view.all',
        'review.assign',
        'review.submit',
        'decision.record',
    ];

    /**
     * NOTE: there is no `site-admin` role here, on purpose.
     *
     * Spatie's teams feature puts journal_id in the PRIMARY KEY of model_has_roles, so
     * every role assignment must name a journal. site-admin is not a relationship to a
     * journal — it is a property of the person — so it lives on users.is_site_admin and
     * is honoured by Gate::before in AppServiceProvider.
     */
    private const ROLES = [
        'publisher-admin' => [
            'journal.view', 'journal.settings.manage', 'journal.issue.manage',
            'journal.article.manage', 'journal.publish', 'journal.doi.deposit',
            'journal.users.manage', 'submission.view.all', 'review.assign', 'decision.record',
        ],

        'journal-editor' => [
            'journal.view', 'journal.settings.manage', 'journal.issue.manage',
            'journal.article.manage', 'journal.publish', 'journal.doi.deposit',
            'journal.users.manage', 'submission.view.all', 'review.assign', 'decision.record',
        ],

        'section-editor' => [
            'journal.view', 'journal.article.manage',
            'submission.view.all', 'review.assign', 'decision.record',
        ],

        // Can prepare everything, cannot make it permanent.
        'production' => [
            'journal.view', 'journal.issue.manage', 'journal.article.manage',
        ],

        'reviewer' => [
            'journal.view', 'review.submit',
        ],

        'author' => [
            'journal.view', 'submission.create', 'submission.view.own',
        ],
    ];

    public function run(): void
    {
        App::make(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::ROLES as $role => $permissions) {
            // setPermissionsTeamId(null) — the role DEFINITION is global; only the
            // ASSIGNMENT of it to a user is scoped to a journal.
            App::make(PermissionRegistrar::class)->setPermissionsTeamId(null);

            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        App::make(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
