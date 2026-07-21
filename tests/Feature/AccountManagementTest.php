<?php

declare(strict_types=1);

use App\Models\Field;
use App\Models\Journal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->journal = Journal::factory()->create(['slug' => 'jcdms', 'title' => 'JCD&MS']);
});

/** grantRoleOn() is a shared helper — see tests/Pest.php. */
function roleNamesOn(User $user, Journal $journal): array
{
    $registrar = App::make(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($journal->id);
    $user->unsetRelation('roles');
    $names = $user->roles->pluck('name')->all();
    $registrar->setPermissionsTeamId(null);

    return $names;
}

// ---------------------------------------------------------------------------
// Login: the bug the user actually hit
// ---------------------------------------------------------------------------

describe('login lands people in the right room', function () {
    it('sends a site admin to the editorial ADMIN, not the empty editorial office', function () {
        $admin = User::factory()->create([
            'is_site_admin' => true,
            'is_active' => true,
            'password' => 'correct-horse-battery',
        ]);

        $this->post('/login', ['email' => $admin->email, 'password' => 'correct-horse-battery'])
            ->assertRedirect(route('admin.dashboard'));
    });

    it('sends a plain author to the editorial office', function () {
        $author = grantRoleOn(
            User::factory()->create(['is_active' => true, 'password' => 'correct-horse-battery']),
            $this->journal,
            'author',
        );

        $this->post('/login', ['email' => $author->email, 'password' => 'correct-horse-battery'])
            ->assertRedirect(route('dashboard'));
    });

    it('sends a journal editor to the admin', function () {
        $editor = grantRoleOn(
            User::factory()->create(['is_active' => true, 'password' => 'correct-horse-battery']),
            $this->journal,
            'journal-editor',
        );

        $this->post('/login', ['email' => $editor->email, 'password' => 'correct-horse-battery'])
            ->assertRedirect(route('admin.dashboard'));
    });

    it('refuses a deactivated account even with the right password', function () {
        $user = User::factory()->create(['is_active' => false, 'password' => 'correct-horse-battery']);

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse-battery'])
            ->assertSessionHasErrors('email');

        expect(auth()->check())->toBeFalse();
    });
});

describe('deactivation takes effect on a live session', function () {
    it('signs out an account deactivated mid-session on its next request', function () {
        $user = grantRoleOn(
            User::factory()->create(['is_active' => true]),
            $this->journal,
            'journal-editor',
        );

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));
        expect(auth()->check())->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// The gates
// ---------------------------------------------------------------------------

describe('who may manage accounts', function () {
    it('lets a site admin reach the accounts screen', function () {
        $admin = User::factory()->create(['is_site_admin' => true]);

        $this->actingAs($admin)->get('/admin/users')->assertOk();
    });

    it('lets a publisher-admin manage accounts but NOT edit role definitions', function () {
        $pub = grantRoleOn(User::factory()->create(), $this->journal, 'publisher-admin');

        $this->actingAs($pub)->get('/admin/users')->assertOk();
        $this->actingAs($pub)->get('/admin/roles')->assertForbidden();
    });

    it('forbids a journal editor from the site-wide accounts screen', function () {
        $editor = grantRoleOn(User::factory()->create(), $this->journal, 'journal-editor');

        $this->actingAs($editor)->get('/admin/users')->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Users CRUD
// ---------------------------------------------------------------------------

describe('creating an account', function () {
    it('creates a user with a hashed password and per-journal roles', function () {
        $admin = User::factory()->create(['is_site_admin' => true]);

        $this->actingAs($admin)->post('/admin/users', [
            'given_name' => 'Renata',
            'family_name' => 'Sørensen',
            'email' => 'renata@example.org',
            'password' => 'twelve-chars-or-more',
            'is_active' => true,
            'assignments' => [
                ['journal_id' => $this->journal->id, 'roles' => ['reviewer']],
            ],
        ])->assertRedirect();

        $user = User::where('email', 'renata@example.org')->firstOrFail();

        expect($user->fullName())->toBe('Renata Sørensen')
            ->and($user->password)->not->toBe('twelve-chars-or-more')
            ->and(password_verify('twelve-chars-or-more', $user->password))->toBeTrue()
            ->and(roleNamesOn($user, $this->journal))->toBe(['reviewer']);
    });

    it('refuses to let a publisher-admin grant site admin', function () {
        $pub = grantRoleOn(User::factory()->create(), $this->journal, 'publisher-admin');

        $this->actingAs($pub)->post('/admin/users', [
            'given_name' => 'Mallory',
            'family_name' => 'Doe',
            'email' => 'mallory@example.org',
            'password' => 'twelve-chars-or-more',
            'is_active' => true,
            'is_site_admin' => true,
        ])->assertRedirect();

        // The field was DISCARDED, not honoured — a forged toggle grants nothing.
        expect(User::where('email', 'mallory@example.org')->firstOrFail()->is_site_admin)->toBeFalse();
    });
});

describe('editing an account', function () {
    it('leaves the password untouched when the field is blank', function () {
        $admin = User::factory()->create(['is_site_admin' => true]);
        $user = User::factory()->create(['password' => 'original-password-x']);
        $before = $user->password;

        $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'given_name' => 'New',
            'family_name' => 'Name',
            'email' => $user->email,
            'password' => '',
            'is_active' => true,
        ])->assertRedirect();

        expect($user->fresh()->password)->toBe($before);
    });

    it('assigns and revokes roles per journal without touching another journal', function () {
        $admin = User::factory()->create(['is_site_admin' => true]);
        $other = Journal::factory()->create(['slug' => 'other']);
        $user = grantRoleOn(User::factory()->create(), $other, 'reviewer');

        $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'given_name' => 'A',
            'family_name' => 'B',
            'email' => $user->email,
            'is_active' => true,
            'assignments' => [
                ['journal_id' => $this->journal->id, 'roles' => ['journal-editor']],
                // `other` is present with an empty array — that revokes there, deliberately.
                ['journal_id' => $other->id, 'roles' => []],
            ],
        ])->assertRedirect();

        expect(roleNamesOn($user->fresh(), $this->journal))->toBe(['journal-editor'])
            ->and(roleNamesOn($user->fresh(), $other))->toBe([]);
    });
});

describe('deleting an account', function () {
    it('hard-deletes an account with no scholarly record', function () {
        $admin = User::factory()->create(['is_site_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin)->delete("/admin/users/{$user->id}")->assertRedirect();

        expect(User::find($user->id))->toBeNull();
    });

    it('refuses to delete your own account', function () {
        $admin = User::factory()->create(['is_site_admin' => true]);

        $this->actingAs($admin)->delete("/admin/users/{$admin->id}")->assertSessionHasErrors();

        expect(User::find($admin->id))->not->toBeNull();
    });

    it('refuses to deactivate the last active site admin', function () {
        $admin = User::factory()->create(['is_site_admin' => true, 'is_active' => true]);
        $other = User::factory()->create(['is_site_admin' => true, 'is_active' => true]);

        // `other` demotes the sole OTHER admin candidate first, then we try to strip admin.
        $this->actingAs($admin)->put("/admin/users/{$other->id}", [
            'given_name' => 'X',
            'family_name' => 'Y',
            'email' => $other->email,
            'is_active' => true,
            'is_site_admin' => false,
        ])->assertRedirect();

        // Now only $admin is a site admin. Demoting them must be refused.
        $this->actingAs($admin)->put("/admin/users/{$admin->id}", [
            'given_name' => 'Z',
            'family_name' => 'W',
            'email' => $admin->email,
            'is_active' => true,
            'is_site_admin' => false,
        ])->assertSessionHasErrors('is_site_admin');

        expect($admin->fresh()->is_site_admin)->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Roles matrix
// ---------------------------------------------------------------------------

describe('role definitions', function () {
    it('lets a site admin change what a role may do', function () {
        $admin = User::factory()->create(['is_site_admin' => true]);

        $this->actingAs($admin)->put('/admin/roles/reviewer', [
            'permissions' => ['journal.view', 'review.submit', 'submission.view.own'],
        ])->assertRedirect();

        App::make(PermissionRegistrar::class)->forgetCachedPermissions();
        App::make(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $reviewer = Spatie\Permission\Models\Role::findByName('reviewer');
        expect($reviewer->permissions->pluck('name')->sort()->values()->all())
            ->toBe(['journal.view', 'review.submit', 'submission.view.own']);
    });

    it('refuses to strip journal.users.manage from publisher-admin', function () {
        $admin = User::factory()->create(['is_site_admin' => true]);

        $this->actingAs($admin)->put('/admin/roles/publisher-admin', [
            'permissions' => ['journal.view'],
        ])->assertSessionHasErrors('permissions');
    });

    it('404s an unknown role name', function () {
        $admin = User::factory()->create(['is_site_admin' => true]);

        $this->actingAs($admin)->put('/admin/roles/superuser', [
            'permissions' => ['journal.view'],
        ])->assertNotFound();
    });
});

// ---------------------------------------------------------------------------
// Fields (subject categories)
// ---------------------------------------------------------------------------

describe('subject fields', function () {
    it('creates a field with a slug derived from its name', function () {
        $admin = User::factory()->create(['is_site_admin' => true]);

        $this->actingAs($admin)->post('/admin/content/fields', [
            'name' => 'Economics & Finance',
        ])->assertRedirect();

        expect(Field::where('name', 'Economics & Finance')->firstOrFail()->slug)
            ->toBe('economics-finance');
    });

    it('refuses to delete a field that still has journals in it', function () {
        $admin = User::factory()->create(['is_site_admin' => true]);
        $field = Field::create(['name' => 'Public Policy', 'slug' => 'public-policy', 'sequence' => 1]);
        $this->journal->update(['field_id' => $field->id]);

        $this->actingAs($admin)->delete("/admin/content/fields/{$field->id}")
            ->assertSessionHasErrors('field');

        expect(Field::find($field->id))->not->toBeNull();
    });

    it('deletes an empty field', function () {
        $admin = User::factory()->create(['is_site_admin' => true]);
        $field = Field::create(['name' => 'Retired Field', 'slug' => 'retired', 'sequence' => 9]);

        $this->actingAs($admin)->delete("/admin/content/fields/{$field->id}")->assertRedirect();

        expect(Field::find($field->id))->toBeNull();
    });

    it('forbids a journal editor from touching fields (site content gate)', function () {
        $editor = grantRoleOn(User::factory()->create(), $this->journal, 'journal-editor');

        $this->actingAs($editor)->post('/admin/content/fields', ['name' => 'Sneaky'])
            ->assertForbidden();
    });
});
