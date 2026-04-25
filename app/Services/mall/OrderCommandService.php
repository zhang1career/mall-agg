<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\CheckoutPhase;
use App\Enums\MallOrderStatus;
use App\Models\MallOrder;
use App\Models\MallOrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orders: draft creation (no inventory) then checkout-driven saga binds remote hold and payment.
 */
final readonly class OrderCommandService
{
    public function __construct(
        private ProductPriceService $prices,
        private ProductInventoryService $inventory,
    ) {}

    /**
     * Step 1: persist pending order + lines; inventory is reserved later in checkout saga.
     *
     * @param  list<array{product_id: int, quantity: int}>  $lines
     */
    public function createDraftPendingOrder(int $userId, array $lines): MallOrder
    {
        if ($lines === []) {
            throw new RuntimeException('Order must contain at least one line.');
        }

        return DB::transaction(function () use ($userId, $lines): MallOrder {
            /** @var array<int, int> $merged */
            $merged = [];
            foreach ($lines as $line) {
                $productId = (int) $line['product_id'];
                $quantity = (int) $line['quantity'];
                if ($productId < 1 || $quantity < 1) {
                    throw new RuntimeException('Invalid order line.');
                }
                $merged[$productId] = ($merged[$productId] ?? 0) + $quantity;
            }

            $total = 0;
            $prepared = [];

            foreach ($merged as $productId => $quantity) {
                $priceRow = $this->prices->getPriceByProductIds([$productId]);
                if (! array_key_exists($productId, $priceRow)) {
                    throw new RuntimeException('Missing price for product '.$productId);
                }
                $unit = $priceRow[$productId];

                $lineTotal = $unit * $quantity;
                $total += $lineTotal;
                $prepared[] = [
                    'pid' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unit,
                ];
            }

            $order = new MallOrder([
                'uid' => $userId,
                'status' => MallOrderStatus::Pending,
                'total_price' => $total,
                'checkout_phase' => CheckoutPhase::None,
                'ext_inventory' => false,
                'ext_id' => '',
            ]);
            $order->save();

            foreach ($prepared as $p) {
                $item = new MallOrderItem([
                    'oid' => $order->id,
                    'pid' => $p['pid'],
                    'quantity' => $p['quantity'],
                    'unit_price' => $p['unit_price'],
                ]);
                $item->save();
            }

            return $order->load('items');
        });
    }

    /**
     * Saga order step: attach remote inventory token to an existing draft order.
     */
    public function bindExternalInventoryToDraftOrder(
        MallOrder $order,
        string $externalReserveId,
        int $sagaIdemKey,
    ): MallOrder {
        $token = trim($externalReserveId);
        if ($token === '') {
            throw new RuntimeException('inventory_token is required.');
        }
        if ($sagaIdemKey < 1) {
            throw new RuntimeException('Invalid saga idem_key.');
        }

        return DB::transaction(function () use ($order, $token, $sagaIdemKey): MallOrder {
            $order->refresh();
            if ($order->status !== MallOrderStatus::Pending) {
                throw new RuntimeException('Order must be pending.');
            }
            if ($order->checkout_phase !== CheckoutPhase::None) {
                throw new RuntimeException('Order is not a draft awaiting inventory bind.');
            }

            $order->ext_inventory = true;
            $order->ext_id = $token;
            $order->saga_idem_key = $sagaIdemKey;
            $order->checkout_phase = CheckoutPhase::OrderCreated;
            $order->save();

            return $order->load('items');
        });
    }

    /**
     * @return list<array{product_id: int, quantity: int}>
     */
    public function linesFromOrderItems(MallOrder $order): array
    {
        $order->loadMissing('items');
        $lines = [];
        foreach ($order->items as $item) {
            $lines[] = [
                'product_id' => (int) $item->pid,
                'quantity' => (int) $item->quantity,
            ];
        }

        if ($lines === []) {
            throw new RuntimeException('Order has no lines.');
        }

        return $lines;
    }

    public function findById(int $orderId): MallOrder
    {
        $order = MallOrder::query()->with('items')->find($orderId);
        if ($order === null) {
            throw (new ModelNotFoundException)->setModel(MallOrder::class, [$orderId]);
        }

        return $order;
    }

    public function transitionStatus(
        MallOrder $order,
        MallOrderStatus $next,
        bool $restoreLocalInventoryOnCancel = false,
    ): MallOrder {
        $current = $order->status;
        if (! $current->canTransitionTo($next)) {
            throw new RuntimeException(
                sprintf('Cannot transition order %d from %s to %s.', $order->id, $current->value, $next->value)
            );
        }

        return DB::transaction(function () use ($order, $next, $current, $restoreLocalInventoryOnCancel): MallOrder {
            $order->load('items');
            if ($current === MallOrderStatus::Pending && $next === MallOrderStatus::Cancelled) {
                if ($restoreLocalInventoryOnCancel) {
                    foreach ($order->items as $item) {
                        $this->inventory->lockAndIncrement($item->pid, $item->quantity);
                    }
                }
            }

            $order->status = $next;
            $order->save();

            return $order;
        });
    }

    /**
     * @return LengthAwarePaginator<int, MallOrder>
     */
    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return MallOrder::query()
            ->where('uid', $userId)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForUser(int $orderId, int $userId): MallOrder
    {
        $order = MallOrder::query()
            ->where('id', $orderId)
            ->where('uid', $userId)
            ->with('items')
            ->first();

        if ($order === null) {
            throw (new ModelNotFoundException)->setModel(MallOrder::class, [$orderId]);
        }

        return $order;
    }
}
