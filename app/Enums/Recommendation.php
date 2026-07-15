<?php

declare(strict_types=1);

namespace App\Enums;

/** What a reviewer recommends. The labels key Dashboard.tsx's REC_STYLE map. */
enum Recommendation: string
{
    case Accept = 'accept';
    case MinorRevision = 'minor_revision';
    case MajorRevision = 'major_revision';
    case Reject = 'reject';

    public function label(): string
    {
        return match ($this) {
            self::Accept => 'Accept',
            self::MinorRevision => 'Minor revision',
            self::MajorRevision => 'Major revision',
            self::Reject => 'Reject',
        };
    }
}
