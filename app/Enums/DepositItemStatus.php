<?php

declare(strict_types=1);

namespace App\Enums;

enum DepositItemStatus: string
{
    case Pending = 'pending';
    case Registered = 'registered';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Registered => 'Registered',
            self::Failed => 'Failed',
        };
    }
}
