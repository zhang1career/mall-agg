<?php

declare(strict_types=1);

namespace App\Services\Outbound;

use App\Contracts\PaymentOutboundContract;

/**
 * Placeholder until payment gateway contract is fixed.
 */
final class StubPaymentOutboundClient implements PaymentOutboundContract
{
    public function createPrepay(int $orderId, int $amountMinor, int $uid): array
    {
        return [
            'order_id' => $orderId,
            'amount_minor' => $amountMinor,
            'uid' => $uid,
            'status' => 'stub_await_payment',
        ];
    }

    public function cancelPrepay(int $orderId): void {}
}
