<?php

declare(strict_types=1);

namespace App\Enums;

enum MallOrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => $next === self::Paid || $next === self::Cancelled,
            self::Paid => false,
            self::Cancelled => false,
        };
    }

    public static function fromClient(string $value): self
    {
        $normalized = strtolower(trim($value));
        if ($normalized === 'init') {
            return self::Pending;
        }
        if ($normalized === 'cancel') {
            return self::Cancelled;
        }

        return self::from($normalized);
    }
}
