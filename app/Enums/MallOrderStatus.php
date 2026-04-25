<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasDictionaryLabel;
use ValueError;

enum MallOrderStatus: int implements HasDictionaryLabel
{
    case Pending = 0;
    case Paid = 1;
    case Cancelled = 2;

    /**
     * @return list<int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'pending',
            self::Paid => 'paid',
            self::Cancelled => 'cancelled',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => $next === self::Paid || $next === self::Cancelled,
            self::Paid => false,
            self::Cancelled => false,
        };
    }

    public static function fromClient(string|int $value): self
    {
        if (is_int($value)) {
            return self::from($value);
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new ValueError('Empty MallOrderStatus.');
        }

        if (ctype_digit($trimmed)) {
            return self::from((int) $trimmed);
        }

        $normalized = strtolower($trimmed);
        if ($normalized === 'init') {
            return self::Pending;
        }
        if ($normalized === 'cancel') {
            return self::Cancelled;
        }

        return match ($normalized) {
            'pending' => self::Pending,
            'paid' => self::Paid,
            'cancelled' => self::Cancelled,
            default => throw new ValueError('Invalid MallOrderStatus: '.$trimmed),
        };
    }
}
