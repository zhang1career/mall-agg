<?php

declare(strict_types=1);

namespace App\Services\Mall;

use App\Enums\MallOrderStatus;
use App\Models\MallOrder;
use App\Models\MallOrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class OrderCommandService
{
    public function __construct(
        private readonly ProductPriceService $prices,
        private readonly ProductInventoryService $inventory,
    ) {}

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

    public function transitionStatus(MallOrder $order, MallOrderStatus $next): MallOrder
    {
        $current = $order->status;
        if (! $current->canTransitionTo($next)) {
            throw new RuntimeException(
                sprintf('Cannot transition order %d from %s to %s.', $order->id, $current->value, $next->value)
            );
        }

        return DB::transaction(function () use ($order, $next, $current): MallOrder {
            $order->load('items');
            if ($current === MallOrderStatus::Pending && $next === MallOrderStatus::Cancelled) {
                foreach ($order->items as $item) {
                    $this->inventory->lockAndIncrement((int) $item->pid, (int) $item->quantity);
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
    public function paginateForUser(int $userId, int $perPage = 15)
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
