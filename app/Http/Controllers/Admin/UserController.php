<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\User;
use App\Support\AdminChrome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Per-journal role assignment. Roles are PER JOURNAL — someone edits Journal A and reviews
 * for Journal B, and a global role cannot express that.
 *
 * setPermissionsTeamId($journal->id) BEFORE syncRoles() IS NOT OPTIONAL. Without it Spatie
 * attaches the role with a NULL team, which reads as "on every journal": one careless save
 * and a reviewer on this journal becomes an editor on all of them. It is the same dance
 * JcdmsSeeder::seedUsers does, for the same reason.
 *
 * The team id is RESTORED afterwards, because the registrar is a singleton for the whole
 * request and the next authorisation check in it would otherwise answer for this journal.
 */
final class UserController extends Controller
{
    /** The roles that mean something on a journal. `site-admin` is not one of them — it is a column. */
    private const ASSIGNABLE = [
        'publisher-admin',
        'journal-editor',
        'section-editor',
        'production',
        'reviewer',
        'author',
    ];

    public function index(Request $request, Journal $journal): Response
    {
        $this->authorize('manageUsers', $journal);

        $members = $this->withTeam($journal, fn (): array => $journal->users()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->fullName(),
                'email' => $user->email,
                'isActive' => (bool) $user->is_active,
                'isSiteAdmin' => (bool) $user->is_site_admin,

                // Scoped to THIS journal by the team context set around this closure. The
                // same person may hold an entirely different set on the next journal.
                'roles' => $this->rolesOn($user),
            ])
            ->values()
            ->all());

        $memberIds = collect($members)->pluck('id')->all();

        return Inertia::render('Admin/Users', array_merge(
            AdminChrome::for($request->user(), $journal),
            [
                'members' => $members,

                'candidates' => User::query()
                    ->where('is_active', true)
                    ->whereKeyNot($memberIds ?: [0])
                    ->orderBy('name')
                    ->limit(200)
                    ->get()
                    ->map(fn (User $user): array => [
                        'id' => $user->id,
                        'name' => $user->fullName(),
                        'email' => $user->email,
                    ])
                    ->values()
                    ->all(),

                'roles' => $this->roleOptions(),

                'meta' => [
                    'title' => 'People — '.$journal->title,
                    'description' => 'Who does what on this journal.',
                ],
            ],
        ));
    }

    public function update(Request $request, Journal $journal, User $user): RedirectResponse
    {
        $this->authorize('manageUsers', $journal);

        $data = $request->validate([
            'roles' => ['array'],
            'roles.*' => [Rule::in(self::ASSIGNABLE)],
        ]);

        $roles = array_values(array_unique($data['roles'] ?? []));

        $this->withTeam($journal, function () use ($user, $roles): void {
            // Eloquent has already cached this user's roles from whichever team the last
            // check ran under. Sync against a stale relation and roles from another journal
            // get detached along the way.
            $user->unsetRelation('roles')->unsetRelation('permissions');

            // An empty array is a legitimate instruction: it removes the person from this
            // journal entirely, and touches no other journal's assignment.
            $user->syncRoles($roles);
        });

        return back()->with('success', $roles === []
            ? "{$user->fullName()} no longer has a role on this journal."
            : "{$user->fullName()}: ".implode(', ', $roles).'.');
    }

    /** @return array<int, string> */
    private function rolesOn(User $user): array
    {
        return $user->unsetRelation('roles')
            ->roles
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * Run a closure with the permission registrar pointed at this journal, and put it back
     * afterwards no matter what happens.
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function withTeam(Journal $journal, \Closure $callback)
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        try {
            $registrar->setPermissionsTeamId($journal->id);

            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
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
}
