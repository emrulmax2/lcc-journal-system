<?php

declare(strict_types=1);

use App\Models\Journal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * MY ACCOUNT — the signed-in person editing themselves.
 *
 * The load-bearing property is what this route CANNOT do: it must never become a
 * self-service route to is_active, is_site_admin or a role, all of which live behind
 * `manage-users` on Admin\AccountController. Those are the tests at the bottom, and they are
 * the reason this file exists — the happy path is the easy part.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->journal = Journal::factory()->create(['slug' => 'jcdms', 'is_active' => true]);
});

/** A plain author: no editorial role anywhere, which is most of the platform's users. */
function plainAuthor(array $attributes = []): User
{
    return grantRoleOn(
        User::factory()->create($attributes + ['is_active' => true]),
        test()->journal,
        'author',
    );
}

it('turns a guest away', function () {
    $this->get('/account')->assertRedirect('/login');
});

it('shows a plain author their own details', function () {
    $author = plainAuthor([
        'given_name' => 'Ana',
        'family_name' => 'Ramírez',
        'email' => 'ana@example.org',
        'affiliation' => 'London Churchill College',
    ]);

    $response = $this->actingAs($author)->get('/account');

    $response->assertOk();

    $account = pageProps($response)['account'];

    expect($account['givenName'])->toBe('Ana')
        // utf8mb4 all the way through: a diacritic must survive the round trip to the prop.
        ->and($account['familyName'])->toBe('Ramírez')
        ->and($account['email'])->toBe('ana@example.org')
        ->and($account['affiliation'])->toBe('London Churchill College');
});

it('never lets the account page be indexed', function () {
    $response = $this->actingAs(plainAuthor())->get('/account');

    // Behind auth, so no crawler reaches it — but the flag is the same one the article
    // previews use, and getting it wrong is silent.
    expect($response->viewData('indexable'))->toBeFalse();
});

it('saves name, email, affiliation and orcid', function () {
    $author = plainAuthor(['given_name' => 'Ana', 'family_name' => 'Ramirez']);

    $this->actingAs($author)
        ->put('/account', [
            'given_name' => 'Ana',
            'family_name' => 'Ramírez',
            'email' => 'ana.ramirez@example.org',
            'affiliation' => 'London Churchill College',
            'orcid' => '0000-0002-1825-0097',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $author->refresh();

    expect($author->family_name)->toBe('Ramírez')
        ->and($author->email)->toBe('ana.ramirez@example.org')
        ->and($author->orcid)->toBe('0000-0002-1825-0097')
        // `name` is the display fallback and must not be left pointing at the old spelling.
        ->and($author->name)->toBe('Ana Ramírez');
});

it('refuses an email already held by someone else', function () {
    User::factory()->create(['email' => 'taken@example.org', 'is_active' => true]);
    $author = plainAuthor(['email' => 'mine@example.org']);

    $this->actingAs($author)
        ->put('/account', [
            'given_name' => 'Ana',
            'family_name' => 'Ramírez',
            'email' => 'taken@example.org',
        ])
        ->assertSessionHasErrors('email');

    expect($author->refresh()->email)->toBe('mine@example.org');
});

it('accepts my own email unchanged', function () {
    $author = plainAuthor(['email' => 'mine@example.org']);

    // The unique rule must ignore this user's own row, or nobody could ever edit their
    // affiliation without also changing their email.
    $this->actingAs($author)
        ->put('/account', [
            'given_name' => 'Ana',
            'family_name' => 'Ramírez',
            'email' => 'mine@example.org',
            'affiliation' => 'Somewhere else',
        ])
        ->assertSessionHasNoErrors();

    expect($author->refresh()->affiliation)->toBe('Somewhere else');
});

it('refuses a malformed orcid', function () {
    $author = plainAuthor();

    $this->actingAs($author)
        ->put('/account', [
            'given_name' => 'Ana',
            'family_name' => 'Ramírez',
            'email' => $author->email,
            'orcid' => '1234-5678',
        ])
        ->assertSessionHasErrors('orcid');
});

it('folds an empty orcid down to null rather than an empty string', function () {
    $author = plainAuthor(['orcid' => '0000-0002-1825-0097']);

    $this->actingAs($author)->put('/account', [
        'given_name' => 'Ana',
        'family_name' => 'Ramírez',
        'email' => $author->email,
        'orcid' => '',
    ]);

    expect($author->refresh()->orcid)->toBeNull();
});

/* ------------------------------- Password -------------------------------- */

it('changes the password when the current one is right', function () {
    $author = plainAuthor(['password' => 'correct-horse-battery']);

    $this->actingAs($author)
        ->put('/account/password', [
            'current_password' => 'correct-horse-battery',
            'password' => 'a-much-longer-new-one',
            'password_confirmation' => 'a-much-longer-new-one',
        ])
        ->assertSessionHas('success');

    expect(Hash::check('a-much-longer-new-one', $author->refresh()->password))->toBeTrue();
});

it('refuses the change when the current password is wrong', function () {
    $author = plainAuthor(['password' => 'correct-horse-battery']);

    $this->actingAs($author)
        ->put('/account/password', [
            'current_password' => 'not-my-password',
            'password' => 'a-much-longer-new-one',
            'password_confirmation' => 'a-much-longer-new-one',
        ])
        ->assertSessionHasErrors('current_password');

    // An unattended signed-in browser must not be a permanent account takeover.
    expect(Hash::check('correct-horse-battery', $author->refresh()->password))->toBeTrue();
});

it('refuses a new password under 12 characters', function () {
    $author = plainAuthor(['password' => 'correct-horse-battery']);

    $this->actingAs($author)
        ->put('/account/password', [
            'current_password' => 'correct-horse-battery',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertSessionHasErrors('password');
});

it('refuses a mistyped confirmation', function () {
    $author = plainAuthor(['password' => 'correct-horse-battery']);

    $this->actingAs($author)
        ->put('/account/password', [
            'current_password' => 'correct-horse-battery',
            'password' => 'a-much-longer-new-one',
            'password_confirmation' => 'a-much-longer-typo',
        ])
        ->assertSessionHasErrors('password');

    expect(Hash::check('correct-horse-battery', $author->refresh()->password))->toBeTrue();
});

/* --------------------------- NOT a privilege route ------------------------ */

it('ignores is_site_admin posted to the profile form', function () {
    $author = plainAuthor();

    $this->actingAs($author)->put('/account', [
        'given_name' => 'Ana',
        'family_name' => 'Ramírez',
        'email' => $author->email,
        // Forged: the form does not render this. is_site_admin is read by Gate::before and is
        // the one global bypass in the system — a self-service route to it would be the whole
        // permission model undone.
        'is_site_admin' => true,
    ]);

    expect($author->refresh()->is_site_admin)->toBeFalse();
});

it('ignores is_active posted to the profile form', function () {
    $author = plainAuthor();

    $this->actingAs($author)->put('/account', [
        'given_name' => 'Ana',
        'family_name' => 'Ramírez',
        'email' => $author->email,
        'is_active' => false,
    ]);

    expect($author->refresh()->is_active)->toBeTrue();
});

it('ignores role assignments posted to the profile form', function () {
    $author = plainAuthor();

    $this->actingAs($author)->put('/account', [
        'given_name' => 'Ana',
        'family_name' => 'Ramírez',
        'email' => $author->email,
        'assignments' => [
            ['journal_id' => $this->journal->id, 'roles' => ['journal-editor']],
        ],
    ]);

    // Still just an author on the journal — and still locked out of /admin.
    $this->actingAs($author->refresh())->get('/admin')->assertForbidden();
});
