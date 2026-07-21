<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * What each role MEANS — the role × permission matrix.
 *
 * SITE-ADMIN ONLY (see the `manage-roles` gate), and the reason is worth stating plainly:
 * role definitions are team-agnostic (journal_id NULL); only ASSIGNMENTS are per-journal.
 * So ticking one box here changes what `journal-editor` may do on EVERY journal, present
 * and future. A journal-editor of one journal editing that would be redefining authority
 * for journals they have nothing to do with.
 *
 * The role LIST is deliberately fixed. There is no "create a role" here, because the six
 * names are not data — they are referenced by name in JournalPolicy, AdminChrome,
 * UserController::ASSIGNABLE and the seeder. A seventh role created through a web form
 * would carry permissions but no code would ever ask for it, so it would look like it
 * worked and do nothing. Adding a role is a code change.
 *
 * Permissions are likewise fixed: every one is a string some policy asks for. Inventing
 * `journal.frobnicate` here would grant the ability to do nothing at all.
 */
final class RoleController extends Controller
{
    /** Ordered most-privileged first — the order the matrix renders in. */
    private const ROLES = [
        'publisher-admin',
        'journal-editor',
        'section-editor',
        'production',
        'reviewer',
        'author',
    ];

    /**
     * The permission catalogue, grouped for the UI. Every string here is asked for by name
     * somewhere in app/Policies — keep this in step with RolesAndPermissionsSeeder.
     *
     * @var array<string, array<string, string>>
     */
    private const CATALOGUE = [
        'The journal' => [
            'journal.view' => 'See the journal exists. Every role has this.',
            'journal.settings.manage' => 'Edit title, ISSN, DOI prefix and Crossref credentials.',
            'journal.users.manage' => 'Give and take away roles on this journal.',
        ],
        'Content' => [
            'journal.issue.manage' => 'Create and arrange volumes and issues.',
            'journal.article.manage' => 'Create and edit articles, including their metadata and PDF.',
        ],
        'Permanent, and paid for' => [
            'journal.publish' => 'Publish. Freezes the URL and the DOI suffix. There is no undo.',
            'journal.doi.deposit' => 'Register DOIs at Crossref.',
        ],
        'Peer review' => [
            'submission.create' => 'Submit a manuscript.',
            'submission.view.own' => 'See their own submissions.',
            'submission.view.all' => 'See every submission in the journal.',
            'review.assign' => 'Invite reviewers — and see who they are.',
            'review.submit' => 'File a review they were invited to write.',
            'decision.record' => 'Accept, reject or request revisions.',
        ],
    ];

    public function index(): Response
    {
        Gate::authorize('manage-roles');

        // The role definition is global. Reading it under a stale team id would filter the
        // rows to one journal and show an empty matrix.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $roles = Role::query()
            ->with('permissions')
            ->whereIn('name', self::ROLES)
            ->get()
            ->sortBy(fn (Role $role): int => array_search($role->name, self::ROLES, true))
            ->map(fn (Role $role): array => [
                'name' => $role->name,
                'description' => $this->describe($role->name),
                'permissions' => $role->permissions->pluck('name')->values()->all(),
                'holders' => $this->holderCount($role),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Roles', [
            'roles' => $roles,
            'catalogue' => $this->catalogue(),

            'meta' => [
                'title' => 'Roles & permissions — '.config('app.name'),
                'description' => 'What each role may do, on every journal it is granted on.',
            ],
        ]);
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        Gate::authorize('manage-roles');

        abort_unless(in_array($role, self::ROLES, true), 404);

        $known = collect(self::CATALOGUE)->flatMap(fn (array $group): array => array_keys($group))->all();

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => [Rule::in($known)],
        ]);

        $permissions = array_values(array_unique($data['permissions'] ?? []));

        /*
         * THE LOCKOUT GUARD.
         *
         * Take journal.users.manage off every role and no non-site-admin can ever grant a
         * role again; take journal.settings.manage off every role and the DOI prefix can
         * never be entered. Both are recoverable only from a SQL client. A settings screen
         * that can quietly disable itself is a trap, so this refuses.
         */
        if ($role === 'publisher-admin' && ! in_array('journal.users.manage', $permissions, true)) {
            throw ValidationException::withMessages([
                'permissions' => 'publisher-admin must keep journal.users.manage — it is the role that grants roles. Without it nobody but a site administrator could ever assign one again.',
            ]);
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $model = Role::query()->where('name', $role)->firstOrFail();
        $model->syncPermissions($permissions);

        // Spatie caches the whole permission map. Without this the change appears to save
        // and then does nothing until the cache expires on its own.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', "Updated what {$role} may do — on every journal it is granted on.");
    }

    /** @return array<int, array{group: string, permissions: array<int, array{name: string, description: string}>}> */
    private function catalogue(): array
    {
        // Only permissions that actually exist as rows. One listed here but never seeded
        // would render a tickable box that silently fails to save.
        $existing = Permission::query()->pluck('name')->all();

        return collect(self::CATALOGUE)
            ->map(fn (array $permissions, string $group): array => [
                'group' => $group,
                'permissions' => collect($permissions)
                    ->filter(fn (string $description, string $name): bool => in_array($name, $existing, true))
                    ->map(fn (string $description, string $name): array => [
                        'name' => $name,
                        'description' => $description,
                    ])
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $group): bool => $group['permissions'] !== [])
            ->values()
            ->all();
    }

    private function describe(string $role): string
    {
        return [
            'publisher-admin' => 'Runs the publishing operation. Also the only non-site-admin who may edit site content.',
            'journal-editor' => 'Runs one journal end to end.',
            'section-editor' => 'Handles their section. Cannot publish.',
            'production' => 'Prepares issues and articles. Deliberately cannot publish or deposit DOIs.',
            'reviewer' => 'Reviews manuscripts they are invited to.',
            'author' => 'Submits manuscripts and tracks their own.',
        ][$role] ?? '';
    }

    /**
     * How many people hold this role, across all journals — the "you are about to change
     * this for N people" number. Counted on the pivot directly, because with teams on,
     * $role->users would be scoped to the current team id.
     */
    private function holderCount(Role $role): int
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');

        return (int) DB::table($tables['model_has_roles'])
            ->where('role_id', $role->getKey())
            ->where('model_type', (new User)->getMorphClass())
            ->distinct()
            ->count($columns['model_morph_key']);
    }
}
