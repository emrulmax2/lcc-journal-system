<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

/*
 * WHY THIS FILE EXISTS.
 *
 * The public disk's URL used to be built from APP_URL. That bakes ONE absolute host into
 * every image URL in the app, and the day the browsed host stops matching .env, every cover,
 * hero and avatar on the site silently becomes the neutral placeholder.
 *
 * It is a nasty failure to diagnose because the page still looks RIGHT — Vite's asset()
 * derives its URLs from the current request root, so the CSS loads and the layout is perfect,
 * and only the images, the one thing routed through Storage::url(), are dead. On this project
 * it happened with APP_URL on :8000 (artisan serve) while Apache served the site on :80.
 *
 * No database: these assert configuration, so they still run when MySQL is down.
 */

/**
 * The URL the site would render for an uploaded file.
 *
 * Wrapped because Storage::disk() is typed to the Filesystem CONTRACT, which does not declare
 * url() — it lives on the FilesystemAdapter the local driver actually returns. Calling it
 * directly is correct at runtime (app/Models/Media.php does exactly this) but reads as an
 * undefined method to static analysis. One annotated helper, rather than the same false
 * positive on every line below.
 */
function publicUrl(string $path): string
{
    /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
    $disk = Storage::disk('public');

    return $disk->url($path);
}

it('serves uploaded images from a root-relative url', function () {
    // Resolves against whatever origin served the page — artisan serve, the Apache vhost and
    // cPanel all work with nothing to keep in sync.
    expect(publicUrl('media/demo/management.jpg'))->toBe('/storage/media/demo/management.jpg');
});

it('does not tie image urls to APP_URL', function () {
    // The regression itself: browsing on a host that is not APP_URL must not break images.
    config()->set('app.url', 'http://a-completely-different-host:9999');

    expect(publicUrl('media/demo/hero.jpg'))
        ->not->toContain('9999')
        ->and(publicUrl('media/demo/hero.jpg'))
        ->toBe('/storage/media/demo/hero.jpg');
});

it('lets a subdirectory install override the prefix', function () {
    // The escape hatch for http://localhost/lcc-journal-system/public, where a root-relative
    // /storage would resolve against the wrong place.
    config()->set('filesystems.disks.public.url', '/lcc-journal-system/public/storage');

    // The manager memoises disks, so the instance built from the old config has to go or
    // this asserts nothing.
    Storage::forgetDisk('public');

    expect(publicUrl('media/demo/finance.jpg'))
        ->toBe('/lcc-journal-system/public/storage/media/demo/finance.jpg');
});
