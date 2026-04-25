<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasDictionaryLabel;

enum PointsHoldState: int implements HasDictionaryLabel
{
    case TryPending = 10;
    case TrySucceeded = 20;
    case Confirmed = 40;
    case RolledBack = 50;

    /** Manual console / API ledger entry (not part of TCC try/confirm/cancel). */
    case AdminLedger = 60;

    public function label(): string
    {
        return match ($this) {
            self::TryPending => 'try pending',
            self::TrySucceeded => 'try succeeded',
            self::Confirmed => 'confirmed',
            self::RolledBack => 'rolled back',
            self::AdminLedger => 'admin ledger',
        };
    }
}
