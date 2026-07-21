<?php

declare(strict_types=1);

use App\Models\Journal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/*
 * The role-gated links in the account dropdown are driven by two shared props on auth.user.
 * They must answer the same questions /admin and the content gate ask — so a link never shows
 * a door the server would then close.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->journal = Journal::factory()->create(['slug' => 'jcdms', 'is_active' => true]);
});

function authUser(TestResponse $response): array
{
    return pageProps($response)['auth']['user'];
}

it('gives a plain author no admin links', function () {
    $author = grantRoleOn(User::factory()->create(['is_active' => true]), $this->journal, 'author');

    $user = authUser($this->actingAs($author)->get('/articles'));

    expect($user['canAccessAdmin'])->toBeFalse()
        ->and($user['canManageSiteContent'])->toBeFalse()
        ->and($user['canManageAccounts'])->toBeFalse();
});

it('gives a journal editor the Editorial admin link', function () {
    $editor = grantRoleOn(User::factory()->create(['is_active' => true]), $this->journal, 'journal-editor');

    expect(authUser($this->actingAs($editor)->get('/articles'))['canAccessAdmin'])->toBeTrue();
});

it('gives a publisher-admin the Site content link', function () {
    $publisher = grantRoleOn(User::factory()->create(['is_active' => true]), $this->journal, 'publisher-admin');

    // manage-site-content is granted to any publisher-admin (AppServiceProvider gate).
    expect(authUser($this->actingAs($publisher)->get('/articles'))['canManageSiteContent'])->toBeTrue();
});

it('gives a site admin all of them', function () {
    $admin = User::factory()->create(['is_active' => true, 'is_site_admin' => true]);

    $user = authUser($this->actingAs($admin)->get('/articles'));

    expect($user['canAccessAdmin'])->toBeTrue()
        ->and($user['canManageSiteContent'])->toBeTrue()
        ->and($user['canManageAccounts'])->toBeTrue();
});
