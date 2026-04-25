<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasDictionaryLabel;

enum CheckoutPhase: int implements HasDictionaryLabel
{
    /** Not in coordinator flow / unset */
    case None = 0;
    /** Coordinator checkout in progress */
    case CoordinatorStarted = 10;
    case InventoryReserved = 20;
    case OrderCreated = 30;
    case PointsTryPending = 40;
    case AwaitPayment = 50;
    case Completed = 60;

    public function label(): string
    {
        return match ($this) {
            self::None => 'none',
            self::CoordinatorStarted => 'coordinator started',
            self::InventoryReserved => 'inventory reserved',
            self::OrderCreated => 'order created',
            self::PointsTryPending => 'points try pending',
            self::AwaitPayment => 'await payment',
            self::Completed => 'completed',
        };
    }
}
