<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Phase 4 — the interface speaks more than English.
 *
 * The locale is resolved server-side (SetLocale) and the full message bag for it is shared to
 * React, so translated chrome is server-rendered. These tests pin the resolution order, the
 * switch endpoint, and that the served HTML actually changes language.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('resolves ?lang= and shares that locale and its translations to the page', function () {
    $response = $this->get('/articles?lang=es')->assertOk();

    $props = pageProps($response);

    expect($props['locale'])->toBe('es')
        ->and($props['translations']['article']['cite'])->toBe('Citar');
});

it('renders the html lang attribute for the active locale', function () {
    $this->get('/articles?lang=fr')
        ->assertOk()
        ->assertSee('lang="fr"', false);
});

it('falls back to the default locale for an unknown ?lang=', function () {
    $response = $this->get('/articles?lang=zz')->assertOk();

    expect(pageProps($response)['locale'])->toBe('en');
});

it('remembers a chosen language across requests via the session', function () {
    $this->get('/locale/es')->assertRedirect();

    // A later request with no ?lang= still gets Spanish, from the session.
    expect(pageProps($this->get('/articles'))['locale'])->toBe('es');
});

it('saves the choice on a signed-in account so it follows them', function () {
    $user = User::factory()->create(['preferred_locale' => null, 'is_active' => true]);

    $this->actingAs($user)->get('/locale/fr')->assertRedirect();

    expect($user->fresh()->preferred_locale)->toBe('fr');
});

it('404s an unsupported locale on the switch route', function () {
    $this->get('/locale/de')->assertNotFound();
});
