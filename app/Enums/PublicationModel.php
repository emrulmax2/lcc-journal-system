<?php

declare(strict_types=1);

namespace App\Enums;

enum PublicationModel: string
{
    /** Articles appear as they are ready. No volumes, no issues, no page numbers. */
    case Continuous = 'continuous';

    /** Articles are collected into a numbered, paginated issue. JCD&MS works this way. */
    case IssueBased = 'issue_based';

    public function label(): string
    {
        return match ($this) {
            self::Continuous => 'Continuous publication',
            self::IssueBased => 'Volumes and issues',
        };
    }

    public function usesIssues(): bool
    {
        return $this === self::IssueBased;
    }
}
