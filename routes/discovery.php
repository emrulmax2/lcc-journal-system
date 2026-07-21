<?php

declare(strict_types=1);

use App\Http\Controllers\OaiPmhController;
use App\Http\Controllers\Public\FeedController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Discovery — machine-readable surfaces
|--------------------------------------------------------------------------
| Consumed exclusively by harvesters (DOAJ, BASE, CORE, OpenAIRE), none of
| which execute JavaScript. Rendered server-side in PHP, like every other
| public surface here.
|
| Kept in their own file so the discovery endpoints stay grouped with the
| reason they exist — but REQUIRED FROM web.php, above the /{page} catch-all,
| never from a `then:` callback in bootstrap/app.php. `then` runs after
| web.php, which would register /oai below the catch-all and let the CMS page
| lookup swallow it.
*/

Route::get('/oai', OaiPmhController::class)->name('oai');

// Atom syndication — the whole site, and each journal. How readers and aggregators follow new
// work without polling. Declared here, above the /{page} catch-all, for the same reason /oai is.
Route::get('/feed', [FeedController::class, 'site'])->name('feed');
Route::get('/journals/{journal:slug}/feed', [FeedController::class, 'journal'])->name('journals.feed');
