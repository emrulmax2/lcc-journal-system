<?php

declare(strict_types=1);

namespace App\Enums;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /**
     * Once an article has been published its identifiers are public promises. A
     * withdrawal does NOT unfreeze them — the landing page must keep resolving, with
     * a withdrawal notice, or the DOI dies. See ArticleObserver.
     */
    public function isFrozen(): bool
    {
        return $this === self::Published || $this === self::Withdrawn;
    }
}
