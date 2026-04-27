<?php

declare(strict_types=1);

namespace App\Contracts;

interface PaymentOutboundContract
{
    /**
     * @return array<string, mixed> Channel-specific prepay payload (e.g. pay_params)
     */
    public function prepay(string $idemKey, int $orderId, int $amountMinor, int $uid): array;

    /**
     * Best-effort undo of {@see prepay} when a distributed transaction rolls back before payment completes.
     */
    public function cancel(int $orderId): void;
}
