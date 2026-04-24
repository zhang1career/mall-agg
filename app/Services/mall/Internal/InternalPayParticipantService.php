<?php

declare(strict_types=1);

namespace App\Services\mall\Internal;

use App\Contracts\PaymentOutboundContract;
use App\Enums\MallOrderStatus;
use App\Services\mall\OrderCommandService;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Saga-facing payment prepay: delegates to {@see PaymentOutboundContract}.
 * Production: WeChat (or other PSP) prepay is created here via the outbound client implementation.
 */
final class InternalPayParticipantService
{
    private const TRY_CACHE = 'saga:pay:try_resp:';

    private const CACHE_TTL_SECONDS = 86_400;

    public function __construct(
        private readonly OrderCommandService $orders,
        private readonly PaymentOutboundContract $payment,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function actionPhase(int $orderId, string $sagaStepIdemKey): array
    {
        if (trim($sagaStepIdemKey) === '') {
            throw new RuntimeException('saga_step_idem_key is required.');
        }

        $idem = trim($sagaStepIdemKey);
        $cacheKey = self::TRY_CACHE.$idem;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $order = $this->orders->findById($orderId);
        if ($order->status !== MallOrderStatus::Pending) {
            throw new RuntimeException('Order must be pending to start payment.');
        }

        $amount = (int) $order->cash_payable_minor;
        if ($amount < 1) {
            $amount = (int) $order->total_price - (int) $order->points_deduct_minor;
        }
        if ($amount < 1) {
            throw new RuntimeException('Order has no payable cash amount for prepay.');
        }

        $prepay = $this->payment->createPrepay($orderId, $amount, (int) $order->uid);
        $out = array_merge(['order_id' => $orderId], $prepay);
        Cache::put($cacheKey, $out, self::CACHE_TTL_SECONDS);

        return $out;
    }

    public function compensatePhase(int $orderId, string $sagaStepIdemKey): void
    {
        if (trim($sagaStepIdemKey) === '') {
            throw new RuntimeException('saga_step_idem_key is required.');
        }

        $idem = trim($sagaStepIdemKey);
        Cache::forget(self::TRY_CACHE.$idem);
        $this->payment->cancelPrepay($orderId);
    }
}
