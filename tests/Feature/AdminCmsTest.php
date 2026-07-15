<?php

declare(strict_types=1);

use App\Models\HomeSection;
use App\Models\Journal;
use App\Models\Media;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Content\MediaLibrary;
use App\Services\Content\SiteContent;
use Database\Seeders\CmsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // The media library writes to the `public` disk. Nothing here touches a real filesystem.
    Storage::fake('public');
});

/** Every GET screen behind the site-content gate. */
function contentScreens(): array
{
    return [
        '/admin/content/settings',
        '/admin/content/pages',
        '/admin/content/pages/create',
        '/admin/content/menus',
        '/admin/content/home',
        '/admin/content/news',
        '/admin/content/news/create',
        '/admin/content/topics',
        '/admin/content/topics/create',
        '/admin/content/media',
        '/admin/content/newsletter',
    ];
}

/** Someone who may edit site content: publisher-admin on ANY journal. */
function publisherAdmin(): User
{
    $journal = Journal::factory()->create();

    return grantRoleOn(User::factory()->create(), $journal, 'publisher-admin');
}

/* -------------------------------------------------------------------------- */
/* Authorisation */
/* -------------------------------------------------------------------------- */

describe('authorisation', function () {
    it('403s on every /admin/content route for a user with no site-content permission', function () {
        $user = User::factory()->create();

        foreach (contentScreens() as $screen) {
            $this->actingAs($user)->get($screen)->assertForbidden();
        }

        // And every mutation, not merely the screens — a hidden button is a courtesy, and the
        // endpoint is the control.
        $this->actingAs($user)->put('/admin/content/settings', ['values' => []])->assertForbidden();
        $this->actingAs($user)->post('/admin/content/preview', ['markdown' => 'x'])->assertForbidden();
        $this->actingAs($user)->post('/admin/content/media', [])->assertForbidden();
        $this->actingAs($user)->get('/admin/content/newsletter/export')->assertForbidden();
    });

    /**
     * The case the gate exists for. A journal-editor runs a journal; they do not own the
     * publisher's privacy policy, and "may you edit it?" has no per-journal answer.
     */
    it('403s for a journal-editor, who has every journal permission but is not the publisher', function () {
        $journal = Journal::factory()->create();
        $editor = grantRoleOn(User::factory()->create(), $journal, 'journal-editor');

        $this->actingAs($editor)->get('/admin/content/settings')->assertForbidden();
        $this->actingAs($editor)->get('/admin/content/media')->assertForbidden();
    });

    it('allows a publisher-admin on any journal', function () {
        $this->seed(CmsSeeder::class);

        $this->actingAs(publisherAdmin())
            ->get('/admin/content/settings')
            ->assertOk();
    });

    it('allows a site admin, who holds no journal role at all', function () {
        $admin = User::factory()->create(['is_site_admin' => true]);

        $this->actingAs($admin)->get('/admin/content/media')->assertOk();
    });

    /**
     * Every screen actually RENDERS, against the real seeded content.
     *
     * A 403 test alone would pass just as happily against a controller that throws — and the
     * one most likely to is Menus, whose every row calls MenuItem::url(), which calls route()
     * on a stored name. If a route is ever renamed out from under a seeded menu item, this is
     * the test that goes red instead of the live navbar.
     */
    it('renders every content screen for a publisher-admin', function () {
        $this->seed(CmsSeeder::class);

        $user = publisherAdmin();

        foreach (contentScreens() as $screen) {
            $this->actingAs($user)->get($screen)->assertOk();
        }
    });

    it('redirects a guest to login rather than 403ing', function () {
        $this->get('/admin/content/settings')->assertRedirect('/login');
    });
});

/* -------------------------------------------------------------------------- */
/* Menus */
/* -------------------------------------------------------------------------- */

describe('menu items', function () {
    /**
     * THE 500 THIS PREVENTS.
     *
     * MenuItem::saving() throws an InvalidArgumentException for a route that does not exist,
     * because a named route that has been renamed is a 404 the moment a reader clicks it and
     * nothing else in the system would ever tell us. Uncaught, that exception is a 500 and a
     * stack trace where a message on a field belongs.
     */
    it('refuses a menu item pointing at a route that does not exist — 422, not 500', function () {
        $menu = Menu::factory()->create();

        $this->actingAs(publisherAdmin())
            ->postJson("/admin/content/menus/{$menu->id}/items", [
                'label' => 'Careers',
                'route_name' => 'careers.index',   // does not exist
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['route_name']);

        expect(MenuItem::count())->toBe(0);
    });

    /** The model is the backstop, and it throws rather than saving a link to nowhere. */
    it('throws at the model layer for a non-existent route, whatever the controller does', function () {
        $menu = Menu::factory()->create();

        expect(fn () => MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Careers',
            'route_name' => 'careers.index',
        ]))->toThrow(InvalidArgumentException::class);
    });

    /**
     * An item with two destinations resolves to whichever the accessor checks first, and the
     * editor sees a link that goes somewhere they did not choose. That is how "Careers" ends
     * up on the homepage.
     */
    it('refuses a menu item that points at two destinations at once', function () {
        $menu = Menu::factory()->create();
        $page = Page::factory()->published()->create();

        $this->actingAs(publisherAdmin())
            ->postJson("/admin/content/menus/{$menu->id}/items", [
                'label' => 'Two places at once',
                'page_id' => $page->id,
                'external_url' => 'https://example.org',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['destination']);

        expect(MenuItem::count())->toBe(0);
    });

    it('refuses a menu item that points nowhere', function () {
        $menu = Menu::factory()->create();

        $this->actingAs(publisherAdmin())
            ->postJson("/admin/content/menus/{$menu->id}/items", ['label' => 'Nowhere'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['destination']);
    });

    it('saves an item pointing at exactly one destination, and resolves its url', function () {
        $menu = Menu::factory()->create();
        $page = Page::factory()->published()->create(['slug' => 'author-guidelines']);

        $this->actingAs(publisherAdmin())
            ->post("/admin/content/menus/{$menu->id}/items", [
                'label' => 'Author guidelines',
                'page_id' => $page->id,
            ])
            ->assertRedirect();

        $item = MenuItem::firstOrFail();

        expect($item->url())->toBe(route('pages.show', 'author-guidelines'))
            ->and($item->external_url)->toBeNull()
            ->and($item->route_name)->toBeNull();
    });
});

/* -------------------------------------------------------------------------- */
/* Pages */
/* -------------------------------------------------------------------------- */

describe('pages', function () {
    /**
     * Deleting a system page does not remove the footer link that points at it — it turns that
     * link into a 404 nothing on the site would ever report.
     */
    it('refuses to delete a system page', function () {
        $page = Page::factory()->system()->published()->create();

        $this->actingAs(publisherAdmin())
            ->delete("/admin/content/pages/{$page->id}")
            ->assertForbidden();

        expect(Page::whereKey($page->id)->exists())->toBeTrue();
    });

    it('deletes an ordinary page', function () {
        $page = Page::factory()->create();

        $this->actingAs(publisherAdmin())
            ->delete("/admin/content/pages/{$page->id}")
            ->assertRedirect('/admin/content/pages');

        expect(Page::whereKey($page->id)->exists())->toBeFalse();
    });

    /** status = published alone does NOT put a page on the site. Both, or it is invisible. */
    it('refuses to publish a page with no publication date', function () {
        $this->actingAs(publisherAdmin())
            ->postJson('/admin/content/pages', [
                'title' => 'Article processing charges',
                'slug' => 'apcs',
                'status' => 'published',
                'published_at' => null,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['published_at']);
    });

    /** A page slugged "journals" is shadowed by /journals and is simply unreachable. */
    it('refuses a slug that collides with an existing route', function () {
        $this->actingAs(publisherAdmin())
            ->postJson('/admin/content/pages', [
                'title' => 'Journals',
                'slug' => 'journals',
                'status' => 'draft',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    });
});

/* -------------------------------------------------------------------------- */
/* Media */
/* -------------------------------------------------------------------------- */

describe('media library', function () {
    /**
     * AN IMAGE WITH NO ALT TEXT IS AN ACCESSIBILITY FAILURE, NOT A TO-DO.
     *
     * London Churchill College is a public-sector body and is legally required to keep this
     * site accessible. NULL alt means nobody has said what the image shows.
     */
    it('rejects an upload with no alt text', function () {
        $this->actingAs(publisherAdmin())
            ->postJson('/admin/content/media', [
                'file' => UploadedFile::fake()->image('lab.jpg', 1200, 800),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['alt']);

        expect(Media::count())->toBe(0);
    });

    /** '' is a DELIBERATE statement — decorative — and is a different thing from NULL. */
    it('accepts a decorative upload, and stores an empty alt rather than a null one', function () {
        $this->actingAs(publisherAdmin())
            ->post('/admin/content/media', [
                'file' => UploadedFile::fake()->image('flourish.png', 400, 400),
                'decorative' => true,
            ])
            ->assertRedirect();

        $media = Media::firstOrFail();

        expect($media->alt)->toBe('')
            ->and($media->needsAltText())->toBeFalse();

        Storage::disk('public')->assertExists($media->path);
    });

    it('stores the image with its dimensions and the uploader', function () {
        $user = publisherAdmin();

        $this->actingAs($user)
            ->post('/admin/content/media', [
                'file' => UploadedFile::fake()->image('cover.jpg', 1600, 900),
                'alt' => 'A soil core being lifted from a riverbank',
            ])
            ->assertRedirect();

        $media = Media::firstOrFail();

        expect($media->width)->toBe(1600)
            ->and($media->height)->toBe(900)
            ->and($media->mime_type)->toBe('image/jpeg')
            ->and($media->uploaded_by)->toBe($user->id)
            ->and($media->path)->toStartWith('media/');
    });

    /**
     * AN SVG IS AN EXECUTABLE DOCUMENT.
     *
     * It is XML that may contain <script>, and a browser runs it when the file is served as
     * image/svg+xml from our own origin. Accepting one would hand every editor a stored-XSS
     * vector on a trusted academic domain — the same hole MarkdownRenderer exists to close,
     * reopened through the upload form.
     */
    it('rejects an SVG upload', function () {
        $this->actingAs(publisherAdmin())
            ->postJson('/admin/content/media', [
                'file' => UploadedFile::fake()->create('logo.svg', 8, 'image/svg+xml'),
                'alt' => 'The logo',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        expect(Media::count())->toBe(0);
    });

    it('rejects an image over 8 MB', function () {
        $this->actingAs(publisherAdmin())
            ->postJson('/admin/content/media', [
                'file' => UploadedFile::fake()->create('huge.jpg', 9000, 'image/jpeg'),
                'alt' => 'Enormous',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    });

    /**
     * THE FKs ARE nullOnDelete. The database would accept this delete and SILENTLY blank the
     * reference — the page would just lose its hero image, with no error and nobody told.
     */
    it('refuses to delete media that is in use, and names what uses it', function () {
        $media = Media::factory()->create();

        $page = Page::factory()->create([
            'title' => 'Author guidelines',
            'hero_media_id' => $media->id,
        ]);

        $response = $this->actingAs(publisherAdmin())
            ->deleteJson("/admin/content/media/{$media->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['media']);

        // It NAMES the use. "In use somewhere" is not an answer an editor can act on.
        expect(json_encode($response->json('errors.media')))
            ->toContain('Author guidelines');

        expect(Media::whereKey($media->id)->exists())->toBeTrue()
            ->and($page->fresh()->hero_media_id)->toBe($media->id);
    });

    it('names a site setting that uses the media', function () {
        $media = Media::factory()->create();

        SiteSetting::create([
            'key' => 'hero_image',
            'group' => 'hero',
            'type' => 'media',
            'label' => 'Hero image',
            'value' => (string) $media->id,
        ]);

        $response = $this->actingAs(publisherAdmin())
            ->deleteJson("/admin/content/media/{$media->id}")
            ->assertStatus(422);

        expect(json_encode($response->json('errors.media')))->toContain('Hero image');
    });

    it('finds every kind of use', function () {
        $media = Media::factory()->create();

        HomeSection::create([
            'key' => 'impact',
            'name' => 'Impact band',
            'media_id' => $media->id,
        ]);

        expect(app(MediaLibrary::class)->usages($media))
            ->toHaveCount(1)
            ->and(app(MediaLibrary::class)->usageMap()[$media->id])
            ->toContain('Homepage section — Impact band');
    });

    it('deletes media that nothing points at, and removes the file', function () {
        $this->actingAs(publisherAdmin())
            ->post('/admin/content/media', [
                'file' => UploadedFile::fake()->image('unused.jpg'),
                'alt' => 'Unused',
            ]);

        $media = Media::firstOrFail();
        $path = $media->path;

        $this->actingAs(publisherAdmin())
            ->delete("/admin/content/media/{$media->id}")
            ->assertRedirect();

        expect(Media::count())->toBe(0);
        Storage::disk('public')->assertMissing($path);
    });
});

/* -------------------------------------------------------------------------- */
/* Markdown preview */
/* -------------------------------------------------------------------------- */

describe('markdown preview', function () {
    /**
     * THE PREVIEW IS THE PUBLIC RENDERER. If these two ever differ, the preview is a promise
     * the live page does not keep — and the difference that matters is this one.
     */
    it('escapes raw HTML rather than rendering it', function () {
        $response = $this->actingAs(publisherAdmin())
            ->postJson('/admin/content/preview', [
                'markdown' => '<script>alert(1)</script>',
            ])
            ->assertOk();

        $html = $response->json('html');

        expect($html)
            ->not->toContain('<script>')
            ->and($html)->not->toContain('</script>')
            ->and($html)->toContain('&lt;script&gt;');
    });

    /**
     * The assertion is about the TAG, not the string.
     *
     * The rendered output does contain the characters `onerror="alert(1)"` — as visible text,
     * inside `&lt;img src=x onerror="alert(1)"&gt;`. That is the correct and safe result: there
     * is no <img> element, so there is no element for the handler to be an attribute OF. What
     * must never appear is a real tag.
     */
    it('escapes an onerror attribute smuggled in on an img tag', function () {
        $html = $this->actingAs(publisherAdmin())
            ->postJson('/admin/content/preview', [
                'markdown' => '<img src=x onerror="alert(1)">',
            ])
            ->json('html');

        expect($html)->not->toContain('<img')
            ->and($html)->toContain('&lt;img');
    });

    it('strips a javascript: link', function () {
        $html = $this->actingAs(publisherAdmin())
            ->postJson('/admin/content/preview', [
                'markdown' => '[click me](javascript:alert(1))',
            ])
            ->json('html');

        expect($html)->not->toContain('href="javascript:');
    });

    it('renders ordinary markdown, including the tables a fee policy needs', function () {
        $html = $this->actingAs(publisherAdmin())
            ->postJson('/admin/content/preview', [
                'markdown' => "## Charges\n\n| Fee | Amount |\n| --- | --- |\n| APC | £0 |",
            ])
            ->json('html');

        expect($html)->toContain('<h2>Charges</h2>')
            ->and($html)->toContain('<table>');
    });
});

/* -------------------------------------------------------------------------- */
/* Newsletter */
/* -------------------------------------------------------------------------- */

describe('newsletter', function () {
    /**
     * THE TOKEN IS A CAPABILITY. Whoever holds it can confirm or unsubscribe that address
     * without ever proving they own the inbox. A leaked list of tokens is a leaked list of
     * consents.
     */
    it('never puts a confirmation token in the subscriber list', function () {
        $subscriber = NewsletterSubscriber::create([
            'email' => 'reader@example.edu',
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs(publisherAdmin())
            ->get('/admin/content/newsletter')
            ->assertOk();

        $payload = pagePropsJson($response);

        expect($payload)
            ->not->toContain('confirmation_token')
            ->and($payload)->not->toContain($subscriber->confirmation_token)
            ->and($payload)->not->toContain('signup_ip')
            ->and($payload)->toContain('reader@example.edu');
    });

    it('counts confirmed, unconfirmed and unsubscribed separately', function () {
        NewsletterSubscriber::create(['email' => 'confirmed@example.edu', 'confirmed_at' => now()]);
        NewsletterSubscriber::create(['email' => 'pending@example.edu']);
        NewsletterSubscriber::create([
            'email' => 'gone@example.edu',
            'confirmed_at' => now()->subMonth(),
            'unsubscribed_at' => now(),
        ]);

        $props = pageProps($this->actingAs(publisherAdmin())->get('/admin/content/newsletter'));

        expect($props['counts'])
            ->toMatchArray(['confirmed' => 1, 'unconfirmed' => 1, 'unsubscribed' => 1, 'total' => 3]);
    });

    /**
     * An address typed into a public box is not consent under UK GDPR/PECR. The export is the
     * moment an unconfirmed one would become an unlawful send, so the scope lives on the
     * server, not in whoever opens the CSV.
     */
    it('exports confirmed subscribers only', function () {
        NewsletterSubscriber::create(['email' => 'confirmed@example.edu', 'confirmed_at' => now()]);
        NewsletterSubscriber::create(['email' => 'never-confirmed@example.edu']);
        NewsletterSubscriber::create([
            'email' => 'unsubscribed@example.edu',
            'confirmed_at' => now()->subMonth(),
            'unsubscribed_at' => now(),
        ]);

        $response = $this->actingAs(publisherAdmin())
            ->get('/admin/content/newsletter/export')
            ->assertOk();

        $csv = $response->streamedContent();

        expect($csv)
            ->toContain('confirmed@example.edu')
            ->and($csv)->not->toContain('never-confirmed@example.edu')
            ->and($csv)->not->toContain('unsubscribed@example.edu')
            ->and($csv)->not->toContain('confirmation_token');
    });
});

/* -------------------------------------------------------------------------- */
/* Settings */
/* -------------------------------------------------------------------------- */

describe('site settings', function () {
    it('validates each row against its own declared type', function () {
        SiteSetting::create(['key' => 'social_x', 'group' => 'social', 'type' => 'url', 'label' => 'X URL']);
        SiteSetting::create(['key' => 'contact_email', 'group' => 'contact', 'type' => 'email', 'label' => 'Contact email']);

        $this->actingAs(publisherAdmin())
            ->putJson('/admin/content/settings', [
                'values' => [
                    'social_x' => 'not-a-url',
                    'contact_email' => 'not-an-email',
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['values.social_x', 'values.contact_email']);
    });

    it('saves through the model, so the SiteContent cache is flushed', function () {
        SiteSetting::create([
            'key' => 'site_name',
            'group' => 'general',
            'type' => 'text',
            'label' => 'Site name',
            'value' => 'Old name',
        ]);

        // Warm the cache, then change the setting through the endpoint.
        app(SiteContent::class)->shared();

        $this->actingAs(publisherAdmin())
            ->put('/admin/content/settings', ['values' => ['site_name' => 'Meridian Open Science']])
            ->assertRedirect();

        expect(app(SiteContent::class)->shared()['settings']['site_name'])
            ->toBe('Meridian Open Science');
    });

    it('stores a boolean as 1 or 0, and an empty string as NULL', function () {
        SiteSetting::create(['key' => 'newsletter_enabled', 'group' => 'footer', 'type' => 'boolean', 'label' => 'Newsletter', 'value' => '1']);
        SiteSetting::create(['key' => 'social_x', 'group' => 'social', 'type' => 'url', 'label' => 'X URL', 'value' => 'https://x.com/lcc']);

        $this->actingAs(publisherAdmin())
            ->put('/admin/content/settings', [
                'values' => ['newsletter_enabled' => false, 'social_x' => ''],
            ])
            ->assertRedirect();

        expect(SiteSetting::where('key', 'newsletter_enabled')->value('value'))->toBe('0')
            // Empty is UNSET, not "". The footer hides a social icon with no URL rather than
            // linking it to "#".
            ->and(SiteSetting::where('key', 'social_x')->value('value'))->toBeNull();
    });
});
