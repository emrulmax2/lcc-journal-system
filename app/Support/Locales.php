<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The languages the interface is available in.
 *
 * ONE list, here. The middleware that resolves a request's locale, the switcher the reader
 * sees, and the validation on the switch route all read it — so "which languages exist" has a
 * single answer and a new language is added in exactly one place (plus its lang/ file).
 *
 * Content (article bodies, abstracts) is NOT translated by this — that is per-article data.
 * This is the chrome, the labels and the editorial interface.
 */
final class Locales
{
    /** code => native name. The native name is what a speaker of that language looks for. */
    public const AVAILABLE = [
        'en' => 'English',
        'es' => 'Español',
        'fr' => 'Français',
    ];

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::AVAILABLE);
    }

    /** @return array<int, array{code: string, name: string}> */
    public static function forMenu(): array
    {
        return array_map(
            fn (string $code, string $name): array => ['code' => $code, 'name' => $name],
            array_keys(self::AVAILABLE),
            array_values(self::AVAILABLE),
        );
    }

    public static function default(): string
    {
        return config('app.locale', 'en');
    }
}
