<?php

declare(strict_types=1);

namespace App\Services\mall\Internal;

use App\Enums\CheckoutPhase;
use App\Enums\MallOrderStatus;
use App\Models\MallOrder;
use App\Services\mall\OrderCommandService;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Saga-facing order lifecycle (action / compensate).
 */
final class InternalOrderParticipantService
{
    private const TRY_RESPONSE_CACHE = 'saga:ord:try_resp:';

    private const CACHE_TTL_SECONDS = 86_400;

    public function __construct(
        private readonly OrderCommandService $orders,
    ) {}

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lines
     * @return array{order_id: int}
     */
    public function actionPhase(
        int $uid,
        array $lines,
        string $inventoryToken,
        string $sagaStepIdemKey,
        ?int $sagaIdemKey = null,
    ): array {
        if ($uid < 1) {
            throw new RuntimeException('Invalid uid.');
        }
        if (trim($sagaStepIdemKey) === '') {
            throw new RuntimeException('saga_step_idem_key is required.');
        }
        $idem = trim($sagaStepIdemKey);
        $cacheKey = self::TRY_RESPONSE_CACHE.$idem;
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['order_id'])) {
            return ['order_id' => (int) $cached['order_id']];
        }

        $token = trim($inventoryToken);
        if ($token === '') {
            throw new RuntimeException('inventory_token is required.');
        }

        if (str_starts_with($token, InternalInventoryParticipantService::localHoldPrefix())) {
            $holdIdem = substr($token, strlen(InternalInventoryParticipantService::localHoldPrefix()));
            if (! Cache::has('saga:inv:local_lines:'.$holdIdem)) {
                throw new RuntimeException('Local inventory hold is missing or expired; run inventory try first.');
            }
        }

        $remote = (bool) config('mall_agg.checkout.use_saga_coordinators', false)
            && ! str_starts_with($token, InternalInventoryParticipantService::localHoldPrefix());

        if ($remote) {
            $order = $this->orders->createOrderWithExternalInventoryReserved($uid, $lines, $token, $sagaIdemKey);
        } else {
            $order = $this->orders->createPendingOrderWithoutInventoryMutation($uid, $lines, $sagaIdemKey, $token);
        }

        $out = ['order_id' => (int) $order->id];
        Cache::put($cacheKey, $out, self::CACHE_TTL_SECONDS);

        return $out;
    }

    public function confirmPaid(int $orderId): MallOrder
    {
        $order = $this->orders->findById($orderId);
        if ($order->status !== MallOrderStatus::Pending) {
            throw new RuntimeException('Order is not pending; cannot confirm paid.');
        }

        return $this->orders->transitionStatus($order, MallOrderStatus::Paid, false);
    }

    public function compensate(int $orderId): MallOrder
    {
        $order = $this->orders->findById($orderId);
        // Saga local inventory is restored by inventory/compensate; avoid double-restore when checkout_phase is past None.
        $restoreLocal = ! $order->ext_inventory && $order->checkout_phase === CheckoutPhase::None;

        return $this->orders->transitionStatus($order, MallOrderStatus::Cancelled, $restoreLocal);
    }
}
