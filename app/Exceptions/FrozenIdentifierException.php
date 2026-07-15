<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when something tries to change an identifier that publication has made
 * permanent. There is no "force" flag and there must never be one: the whole point of
 * a DOI is that it is a promise, and a promise you can quietly edit is not one.
 *
 * If you are reading this because a test failed, the fix is almost never to relax the
 * observer. It is that the calling code is regenerating a slug from a title.
 */
final class FrozenIdentifierException extends RuntimeException
{
    /** @param  array<int, string>  $attributes */
    public static function forArticle(int $articleId, array $attributes): self
    {
        $list = implode(', ', $attributes);

        return new self(
            "Article {$articleId} is published: [{$list}] are frozen and cannot be changed. "
            .'These form the permanent public URL and the registered DOI. Changing one '
            .'breaks every citation, index entry and link that already points at it.'
        );
    }
}
