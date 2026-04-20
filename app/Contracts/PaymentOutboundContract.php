<?php

declare(strict_types=1);

namespace App\Contracts;

interface PaymentOutboundContract
{
    /**
     * @return array<string, mixed> Channel-specific prepay payload (e.g. pay_params)
     */
    public function createPrepay(int $orderId, int $amountMinor, int $uid): array;
}
