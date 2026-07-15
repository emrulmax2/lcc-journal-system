<?php

declare(strict_types=1);

use App\Http\Controllers\OaiPmhController;
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
