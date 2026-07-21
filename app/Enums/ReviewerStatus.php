<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where an invitation has got to. label() must return exactly what Dashboard.tsx expects —
 * 'Report submitted', not 'Report Submitted' — because the component compares the string
 * to decide whether a reviewer is overdue.
 */
enum ReviewerStatus: string
{
    case Invited = 'invited';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case ReportSubmitted = 'report_submitted';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::ReportSubmitted => 'Report submitted',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /** Still owed to the editor — the states an overdue reminder applies to. */
    public function isOutstanding(): bool
    {
        return $this === self::Invited || $this === self::Accepted;
    }
}
