<?php

declare(strict_types=1);

namespace App\Enums;

enum DepositStatus: string
{
    case Queued = 'queued';
    case Depositing = 'depositing';

    /**
     * Accepted by Crossref for processing. NOT the same as registered — Crossref
     * processes deposits asynchronously and a 200 on the POST tells you only that the
     * XML was received. Treating `submitted` as success is how journals end up
     * believing they have DOIs that were in fact rejected.
     */
    case Submitted = 'submitted';

    case Registered = 'registered';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Depositing => 'Depositing',
            self::Submitted => 'Awaiting Crossref',
            self::Registered => 'Registered',
            self::Failed => 'Failed',
        };
    }

    public function isRetryable(): bool
    {
        return $this === self::Failed;
    }

    public function isTerminal(): bool
    {
        return $this === self::Registered;
    }
}
