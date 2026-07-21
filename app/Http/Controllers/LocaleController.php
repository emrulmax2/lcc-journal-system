<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The language switcher's endpoint. Stores the reader's choice in the session, and — if they
 * are signed in — on their account, so it follows them to another device. SetLocale reads
 * both on the next request.
 */
final class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        abort_unless(Locales::isSupported($locale), 404);

        $request->session()->put('locale', $locale);

        if ($user = $request->user()) {
            $user->forceFill(['preferred_locale' => $locale])->save();
        }

        // Back to wherever they were — the language changes under them, the page does not.
        return back();
    }
}
