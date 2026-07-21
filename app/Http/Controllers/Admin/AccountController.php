<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * ACCOUNTS. Site-wide, deliberately — the sibling of, not a replacement for, the per-journal
 * People screen (Admin\UserController).
 *
 * The distinction is the whole design:
 *
 *   Admin\UserController  — "who does what ON THIS JOURNAL". Assigns roles, scoped by team.
 *                           It cannot create a person, because a person is not of a journal.
 *   Admin\AccountController (here) — the person THEMSELVES exists: name, email, password,
 *                           active, site admin. Plus their roles across EVERY journal, which
 *                           is the one view the per-journal screen structurally cannot show.
 *
 * Before this existed there was no way to create a user at all outside a seeder, and the
 * only route to `is_site_admin` was a SQL client.
 *
 * ROLES ARE READ AND WRITTEN UNDER A TEAM CONTEXT (setPermissionsTeamId) exactly as
 * UserController::withTeam does, for exactly the same reason: without it Spatie attaches
 * roles with a NULL team, which reads as "on every journal".
 */
final class AccountController extends Controller
{
    /** Mirrors Admin\UserController::ASSIGNABLE. `site-admin` is not here — it is a column. */
    private const ASSIGNABLE = [
        'publisher-admin',
        'journal-editor',
        'section-editor',
        'production',
        'reviewer',
        'author',
    ];

    public function index(Request $request): Response
    {
        Gate::authorize('manage-users');

        $search = trim((string) $request->string('q'));

        $users = User::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('given_name', 'like', "%{$search}%")
                ->orWhere('family_name', 'like', "%{$search}%")))
            ->orderByDesc('is_site_admin')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $journals = Journal::query()->orderBy('title')->get();

        return Inertia::render('Admin/Accounts', [
            'users' => [
                'data' => collect($users->items())
                    ->map(fn (User $user): array => $this->summarise($user, $journals))
                    ->values()
                    ->all(),
                'links' => $users->linkCollection()->toArray(),
                'total' => $users->total(),
            ],

            'filters' => ['q' => $search],

            'can' => [
                // Whether the site-admin toggle renders at all. A publisher-admin manages
                // accounts but may not mint a global bypass — see the gate.
                'grantSiteAdmin' => $request->user()->can('grant-site-admin'),
                'manageRoles' => $request->user()->can('manage-roles'),
            ],

            'meta' => [
                'title' => 'People — '.config('app.name'),
                'description' => 'Every account on the platform, and what it may do.',
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('manage-users');

        return Inertia::render('Admin/AccountEditor', [
            'account' => null,
            'journals' => $this->journalOptions(),
            'roles' => $this->roleOptions(),
            'assignments' => [],
            'can' => ['grantSiteAdmin' => $request->user()->can('grant-site-admin')],
            'meta' => ['title' => 'New account — '.config('app.name')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-users');

        $data = $request->validate([
            'given_name' => ['required', 'string', 'max:120'],
            'family_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
            'affiliation' => ['nullable', 'string', 'max:255'],
            'orcid' => ['nullable', 'string', 'regex:/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/'],
            'is_active' => ['boolean'],
            'is_site_admin' => ['boolean'],
            'assignments' => ['array'],
            'assignments.*.journal_id' => ['required', 'integer', 'exists:journals,id'],
            'assignments.*.roles' => ['array'],
            'assignments.*.roles.*' => [Rule::in(self::ASSIGNABLE)],
        ], [
            'orcid.regex' => 'An ORCID looks like 0000-0002-1825-0097.',
            'password.min' => 'Use at least 12 characters.',
        ]);

        $user = new User([
            'given_name' => $data['given_name'],
            'family_name' => $data['family_name'],
            // `name` is the login display name and the fallback in fullName(). Derived here
            // so it is never empty for an account created through this screen.
            'name' => trim("{$data['given_name']} {$data['family_name']}"),
            'email' => $data['email'],
            'affiliation' => $data['affiliation'] ?? null,
            // ?? for the absent key (a `nullable` field that validated empty is dropped from
            // the array entirely), then ?: to fold an empty string down to a real null.
            'orcid' => ($data['orcid'] ?? null) ?: null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        // Cast to `hashed` on the model — assigning the plaintext is correct, and
        // double-hashing it here would lock the account out silently.
        $user->password = $data['password'];

        $user->is_site_admin = $this->resolveSiteAdmin($request, false);

        $user->save();

        $this->syncAssignments($user, $data['assignments'] ?? []);

        return redirect()
            ->route('admin.accounts.edit', $user)
            ->with('success', "{$user->fullName()} now has an account.");
    }

    public function edit(Request $request, User $account): Response
    {
        Gate::authorize('manage-users');

        return Inertia::render('Admin/AccountEditor', [
            'account' => [
                'id' => $account->id,
                'givenName' => $account->given_name,
                'familyName' => $account->family_name,
                'name' => $account->name,
                'email' => $account->email,
                'affiliation' => $account->affiliation,
                'orcid' => $account->orcid,
                'isActive' => (bool) $account->is_active,
                'isSiteAdmin' => (bool) $account->is_site_admin,
                'isSelf' => $account->is($request->user()),
                'contentCounts' => $this->contentCounts($account),
            ],

            'journals' => $this->journalOptions(),
            'roles' => $this->roleOptions(),
            'assignments' => $this->assignmentsOf($account),

            'can' => ['grantSiteAdmin' => $request->user()->can('grant-site-admin')],

            'meta' => ['title' => $account->fullName().' — '.config('app.name')],
        ]);
    }

    public function update(Request $request, User $account): RedirectResponse
    {
        Gate::authorize('manage-users');

        $data = $request->validate([
            'given_name' => ['required', 'string', 'max:120'],
            'family_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account->id)],
            // Absent means "leave it alone". An empty string would otherwise be hashed into
            // an unguessable password and lock the account.
            'password' => ['nullable', 'string', 'min:12'],
            'affiliation' => ['nullable', 'string', 'max:255'],
            'orcid' => ['nullable', 'string', 'regex:/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/'],
            'is_active' => ['boolean'],
            'is_site_admin' => ['boolean'],
            'assignments' => ['array'],
            'assignments.*.journal_id' => ['required', 'integer', 'exists:journals,id'],
            'assignments.*.roles' => ['array'],
            'assignments.*.roles.*' => [Rule::in(self::ASSIGNABLE)],
        ], [
            'orcid.regex' => 'An ORCID looks like 0000-0002-1825-0097.',
            'password.min' => 'Use at least 12 characters.',
        ]);

        $account->fill([
            'given_name' => $data['given_name'],
            'family_name' => $data['family_name'],
            'name' => trim("{$data['given_name']} {$data['family_name']}"),
            'email' => $data['email'],
            'affiliation' => $data['affiliation'] ?? null,
            // ?? for the absent key (a `nullable` field that validated empty is dropped from
            // the array entirely), then ?: to fold an empty string down to a real null.
            'orcid' => ($data['orcid'] ?? null) ?: null,
        ]);

        if (filled($data['password'] ?? null)) {
            $account->password = $data['password'];
        }

        $account->is_active = $this->resolveActive($request, $account);
        $account->is_site_admin = $this->resolveSiteAdmin($request, (bool) $account->is_site_admin, $account);

        $account->save();

        $this->syncAssignments($account, $data['assignments'] ?? []);

        return back()->with('success', "{$account->fullName()} updated.");
    }

    /**
     * Deactivate, or — only when the person has left no trace on the record — delete.
     *
     * A user who has authored a submission, filed a review or recorded a decision is PART OF
     * THE SCHOLARLY RECORD. Deleting them would orphan or rewrite it, and the record is the
     * thing this platform exists to keep. So they are deactivated: they cannot sign in (see
     * EnsureAccountIsActive) and their history stays intact and attributable.
     *
     * An account created by mistake ten minutes ago has no such history, and forcing a
     * permanent tombstone row for a typo helps nobody. That is the only case that deletes.
     */
    public function destroy(Request $request, User $account): RedirectResponse
    {
        Gate::authorize('manage-users');

        // Locking yourself out of the admin you are standing in, with a button labelled
        // "Deactivate", is not a decision anybody means to make.
        if ($account->is($request->user())) {
            throw ValidationException::withMessages([
                'account' => 'You cannot deactivate your own account.',
            ]);
        }

        $this->guardLastSiteAdmin($account);

        $counts = $this->contentCounts($account);

        if (array_sum($counts) > 0) {
            $account->forceFill(['is_active' => false])->save();

            return redirect()
                ->route('admin.accounts.index')
                ->with('success', "{$account->fullName()} has been deactivated. Their submissions, reviews and decisions stay on the record.");
        }

        $name = $account->fullName();

        /*
         * Clear the role pivots by RAW DELETE, not $account->roles()->detach().
         *
         * With teams on, the roles() relation is scoped to the current team id, so detach()
         * would only remove pivots for ONE journal (or the null team) and leave this user's
         * assignments on every other journal as orphan rows — model_has_roles has no cascade
         * on the polymorphic model_id to sweep them. One raw delete across all teams is the
         * only thing that actually clears them.
         */
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');

        DB::table($tables['model_has_roles'])
            ->where($columns['model_morph_key'], $account->getKey())
            ->where('model_type', $account->getMorphClass())
            ->delete();

        $account->delete();

        return redirect()
            ->route('admin.accounts.index')
            ->with('success', "{$name}'s account was deleted. They had no submissions, reviews or decisions on record.");
    }

    // --- Guards -------------------------------------------------------------

    /**
     * `is_site_admin` is the one global bypass in the system (Gate::before). It may only be
     * changed by someone who already holds it, or `manage-users` would quietly BE it: a
     * publisher-admin could grant themselves the column and answer true to every policy.
     *
     * Anyone else's submitted value is DISCARDED, not rejected — the field is not rendered
     * for them, so a value arriving here is a forged request, and the right response to a
     * forged field is to ignore it, not to hand back a validation message that teaches the
     * sender what to try next.
     */
    private function resolveSiteAdmin(Request $request, bool $current, ?User $account = null): bool
    {
        if (! $request->user()->can('grant-site-admin')) {
            return $current;
        }

        $next = $request->boolean('is_site_admin');

        // Demoting yourself while you are the only one holding the keys locks every admin
        // screen in the app, for everyone, with no way back short of a SQL client.
        if ($account !== null && ! $next && $current) {
            $this->guardLastSiteAdmin($account);
        }

        return $next;
    }

    private function resolveActive(Request $request, User $account): bool
    {
        $next = $request->boolean('is_active');

        if (! $next && $account->is($request->user())) {
            throw ValidationException::withMessages([
                'is_active' => 'You cannot deactivate your own account.',
            ]);
        }

        if (! $next) {
            $this->guardLastSiteAdmin($account);
        }

        return $next;
    }

    /** The last site admin is load-bearing: without one, nothing can grant the column back. */
    private function guardLastSiteAdmin(User $account): void
    {
        if (! $account->is_site_admin) {
            return;
        }

        $others = User::query()
            ->where('is_site_admin', true)
            ->where('is_active', true)
            ->whereKeyNot($account->getKey())
            ->exists();

        if (! $others) {
            throw ValidationException::withMessages([
                'is_site_admin' => 'This is the last active site administrator. Give someone else the role first.',
            ]);
        }
    }

    // --- Roles --------------------------------------------------------------

    /**
     * Write this person's roles on the named journals, one team context at a time.
     *
     * Journals ABSENT from the payload are left alone — the editor may only have been shown
     * a subset, and "not mentioned" must never mean "revoke". An empty `roles` array for a
     * journal that IS named is the explicit instruction to remove them from it.
     *
     * @param  array<int, array{journal_id: int, roles?: array<int, string>}>  $assignments
     */
    private function syncAssignments(User $user, array $assignments): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        try {
            foreach ($assignments as $assignment) {
                $registrar->setPermissionsTeamId($assignment['journal_id']);

                // Eloquent caches `roles` from whichever team the LAST iteration ran under.
                // Sync against that stale relation and the previous journal's roles are
                // detached along the way — the exact cross-tenant bug JournalPolicy and
                // UserController guard against.
                $user->unsetRelation('roles')->unsetRelation('permissions');
                $user->syncRoles(array_values(array_unique($assignment['roles'] ?? [])));
            }
        } finally {
            // The registrar is a singleton for the whole request. Left pointed at the last
            // journal in the loop, the next authorization check would answer for it.
            $registrar->setPermissionsTeamId($previous);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    /**
     * This person's roles on EVERY journal — the view the per-journal screen cannot give.
     *
     * @return array<int, array{journalId: int, roles: array<int, string>}>
     */
    private function assignmentsOf(User $user): array
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        try {
            return Journal::query()
                ->orderBy('title')
                ->get()
                ->map(function (Journal $journal) use ($user, $registrar): array {
                    $registrar->setPermissionsTeamId($journal->id);
                    $user->unsetRelation('roles')->unsetRelation('permissions');

                    return [
                        'journalId' => $journal->id,
                        'roles' => $user->roles->pluck('name')->values()->all(),
                    ];
                })
                ->values()
                ->all();
        } finally {
            $registrar->setPermissionsTeamId($previous);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    /**
     * @param  Collection<int, Journal>  $journals
     * @return array<string, mixed>
     */
    private function summarise(User $user, $journals): array
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        try {
            $roles = $journals
                ->map(function (Journal $journal) use ($user, $registrar): ?array {
                    $registrar->setPermissionsTeamId($journal->id);
                    $user->unsetRelation('roles')->unsetRelation('permissions');

                    $names = $user->roles->pluck('name')->values()->all();

                    return $names === [] ? null : [
                        'journal' => $journal->abbreviation ?? $journal->title,
                        'roles' => $names,
                    ];
                })
                ->filter()
                ->values()
                ->all();
        } finally {
            $registrar->setPermissionsTeamId($previous);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }

        return [
            'id' => $user->id,
            'name' => $user->fullName(),
            'email' => $user->email,
            'affiliation' => $user->affiliation,
            'isActive' => (bool) $user->is_active,
            'isSiteAdmin' => (bool) $user->is_site_admin,
            'roles' => $roles,
        ];
    }

    /** @return array<int, array{id: int, title: string, abbreviation: string|null}> */
    private function journalOptions(): array
    {
        return Journal::query()
            ->orderBy('title')
            ->get()
            ->map(fn (Journal $journal): array => [
                'id' => $journal->id,
                'title' => $journal->title,
                'abbreviation' => $journal->abbreviation,
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array{name: string, description: string}> */
    private function roleOptions(): array
    {
        $descriptions = [
            'publisher-admin' => 'Everything, including settings, publication and DOI deposits.',
            'journal-editor' => 'Runs the journal: issues, articles, publication, DOIs and people.',
            'section-editor' => 'Handles submissions and articles in their section. Cannot publish.',
            'production' => 'Prepares issues and articles. Deliberately CANNOT publish or deposit DOIs.',
            'reviewer' => 'Reviews manuscripts they are invited to. Sees nothing else.',
            'author' => 'Submits manuscripts and tracks their own.',
        ];

        return Role::query()
            ->whereIn('name', self::ASSIGNABLE)
            ->get()
            ->sortBy(fn (Role $role): int => array_search($role->name, self::ASSIGNABLE, true))
            ->map(fn (Role $role): array => [
                'name' => $role->name,
                'description' => $descriptions[$role->name] ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * What this person has left on the scholarly record. Drives both the delete/deactivate
     * decision and the words the confirm dialog uses.
     *
     * @return array<string, int>
     */
    private function contentCounts(User $user): array
    {
        return [
            'submissions' => $user->submissions()->count(),
            'reviews' => $user->reviewAssignments()->count(),
        ];
    }
}
