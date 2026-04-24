<?php

declare(strict_types=1);

namespace App\Services\mall\Internal;

use App\Enums\MallOrderStatus;
use App\Models\MallOrder;
use App\Services\mall\OrderCommandService;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Saga order step: bind remote inventory token to an existing draft order.
 */
final class InternalOrderParticipantService
{
    private const TRY_RESPONSE_CACHE = 'saga:ord:try_resp:';

    private const CACHE_TTL_SECONDS = 86_400;

    public function __construct(
        private readonly OrderCommandService $orders,
    ) {}

    /**
     * @return array{order_id: int}
     */
    public function bindDraftOrderAfterInventory(
        int $orderId,
        int $uid,
        string $inventoryToken,
        string $sagaStepIdemKey,
        int $sagaIdemKey,
    ): array {
        if ($uid < 1) {
            throw new RuntimeException('Invalid uid.');
        }
        if ($orderId < 1) {
            throw new RuntimeException('Invalid order_id.');
        }
        if (trim($sagaStepIdemKey) === '') {
            throw new RuntimeException('saga_step_idem_key is required.');
        }
        if (trim($inventoryToken) === '') {
            throw new RuntimeException('inventory_token is required.');
        }
        if ($sagaIdemKey < 1) {
            throw new RuntimeException('saga_idem_key is required.');
        }

        $idem = trim($sagaStepIdemKey);
        $cacheKey = self::TRY_RESPONSE_CACHE.$idem;
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['order_id'])) {
            return ['order_id' => (int) $cached['order_id']];
        }

        $order = $this->orders->findById($orderId);
        if ($order->uid !== $uid) {
            throw new RuntimeException('Order does not belong to uid.');
        }

        $this->orders->bindExternalInventoryToDraftOrder($order, $inventoryToken, $sagaIdemKey);

        $out = ['order_id' => $orderId];
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

        return $this->orders->transitionStatus($order, MallOrderStatus::Cancelled, false);
    }
}
