<?php

declare(strict_types=1);

namespace App\Enums;

enum CheckoutPhase: int
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
}
