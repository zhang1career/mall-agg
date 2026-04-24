<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Contracts\InventoryOutboundContract;
use App\Enums\CheckoutPhase;
use App\Enums\MallOrderStatus;
use App\Models\MallOrder;
use App\Models\MallOrderItem;
use App\Services\Transaction\SagaCoordinatorClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final readonly class OrderCommandService
{
    public function __construct(
        private ProductPriceService $prices,
        private ProductInventoryService $inventory,
        private InventoryOutboundContract $inventoryOutbound,
        private SagaCoordinatorClient $sagaCoordinator,
    ) {}

    /**
     * Step 1: create pending order with unified resource lock (local decrement or external reserve per config).
     *
     * @param  list<array{product_id: int, quantity: int}>  $lines
     */
    public function createPendingOrderForCheckout(int $userId, array $lines): MallOrder
    {
        if ((bool) config('mall_agg.checkout.use_saga_coordinators', false)) {
            $reserved = $this->inventoryOutbound->reserve($userId, $lines);
            $reserveId = $reserved['reserve_id'];

            $flowId = (int) config('mall_agg.saga.flow_id', 0);
            $accessKey = trim((string) config('mall_agg.saga.access_key', ''));
            if ($flowId < 1 || $accessKey === '') {
                try {
                    $this->inventoryOutbound->release($reserveId);
                } catch (Throwable) {
                }
                throw new RuntimeException(
                    'Saga coordinator is not configured for checkout. Set MALL_SAGA_FLOW_ID and MALL_SAGA_ACCESS_KEY.'
                );
            }

            try {
                $tccAccessKey = trim((string) config('mall_agg.tcc.access_key', ''));
                $tccFlowId = (int) config('mall_agg.tcc.flow_id', 0);

                $sagaData = $this->sagaCoordinator->start([
                    'access_key' => $accessKey,
                    'flow_id' => $flowId,
                    'context' => [
                        'uid' => $userId,
                        'lines' => $lines,
                        'reserve_id' => $reserveId,
                        'reuse_inventory_token' => $reserveId,
                        'tcc_access_key' => $tccAccessKey,
                        'tcc_flow_id' => $tccFlowId,
                    ],
                    'step_payloads' => [
                        '0' => [
                            'uid' => $userId,
                            'lines' => $lines,
                            'reuse_inventory_token' => $reserveId,
                        ],
                    ],
                ]);
            } catch (Throwable $e) {
                try {
                    $this->inventoryOutbound->release($reserveId);
                } catch (Throwable) {
                }
                throw $e;
            }

            $idemKey = (int) ($sagaData['idem_key'] ?? 0);
            if ($idemKey < 1) {
                try {
                    $this->inventoryOutbound->release($reserveId);
                } catch (Throwable) {
                }
                throw new RuntimeException('Saga start response missing idem_key.');
            }

            return $this->createOrderWithExternalInventoryReserved(
                $userId,
                $lines,
                $reserveId,
                $idemKey,
            );
        }

        return $this->createOrder($userId, $lines);
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lines
     */
    public function createOrder(int $userId, array $lines): MallOrder
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

                $this->inventory->lockAndDecrement($productId, $quantity);

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
     * Create pending order without touching local {@see ProductInventoryService}; external inventory must already be reserved.
     *
     * @param  list<array{product_id: int, quantity: int}>  $lines
     */
    public function createOrderWithExternalInventoryReserved(
        int $userId,
        array $lines,
        string $externalReserveId,
        ?int $sagaIdemKey = null,
    ): MallOrder {
        if ($lines === []) {
            throw new RuntimeException('Order must contain at least one line.');
        }

        return DB::transaction(function () use ($userId, $lines, $externalReserveId, $sagaIdemKey): MallOrder {
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
                'ext_inventory' => true,
                'ext_id' => $externalReserveId,
                'checkout_phase' => CheckoutPhase::OrderCreated,
                'saga_idem_key' => $sagaIdemKey,
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
     * Pending order after inventory was already adjusted by a prior saga step (local decrement or external reserve).
     *
     * @param  list<array{product_id: int, quantity: int}>  $lines
     */
    public function createPendingOrderWithoutInventoryMutation(
        int $userId,
        array $lines,
        ?int $sagaIdemKey = null,
        string $inventoryHoldToken = '',
    ): MallOrder {
        if ($lines === []) {
            throw new RuntimeException('Order must contain at least one line.');
        }

        return DB::transaction(function () use ($userId, $lines, $sagaIdemKey, $inventoryHoldToken): MallOrder {
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
                'checkout_phase' => CheckoutPhase::OrderCreated,
                'ext_inventory' => false,
                'ext_id' => $inventoryHoldToken !== '' ? $inventoryHoldToken : '',
                'saga_idem_key' => $sagaIdemKey,
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
        bool $restoreLocalInventoryOnCancel = true,
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
