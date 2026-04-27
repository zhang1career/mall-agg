<?php

declare(strict_types=1);

namespace App\Services\outbound;

use App\Contracts\PaymentOutboundContract;

/**
 * Placeholder until payment gateway contract is fixed.
 */
final class StubPaymentOutboundClient implements PaymentOutboundContract
{
    public function prepay(string $idemKey, int $orderId, int $amountMinor, int $uid): array
    {
        return [
            'order_id' => $orderId,
            'amount_minor' => $amountMinor,
            'uid' => $uid,
            'status' => 'stub_await_payment',
        ];
    }

    public function cancel(int $orderId): void {}
}
