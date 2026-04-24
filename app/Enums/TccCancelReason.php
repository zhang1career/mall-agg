<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * TCC cancel body field `cancel_reason` (integer). {@see \App\Services\Transaction\TccCoordinatorClient::cancel}
 *
 * Mapping: 0 = unpaid, 10 = order_closed, 20 = duplicate_callback.
 */
enum TccCancelReason: int
{
    /** User did not complete payment or checkout failed before pay success. */
    case Unpaid = 0;

    /** Order was closed or voided by business rules (timeout sweep, manual cancel, etc.). */
    case OrderClosed = 10;

    /** Idempotent duplicate notification; do not treat as a new commit attempt. */
    case DuplicateCallback = 20;
}
