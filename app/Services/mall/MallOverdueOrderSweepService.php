<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Contracts\InventoryOutboundContract;
use App\Enums\CheckoutPhase;
use App\Enums\MallOrderStatus;
use App\Enums\PointsHoldState;
use App\Enums\TccCancelReason;
use App\Models\MallOrder;
use App\Models\PointsFlow;
use App\Services\Transaction\TccCoordinatorClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Closes pending mall orders that exceeded the payment window (XXL-Job / scheduled maintenance).
 *
 * Compensation order matches {@see CheckoutOrchestrator} catch: TCC cancel → order cancel (+ ext release) → local points cancel.
 */
final readonly class MallOverdueOrderSweepService
{
    public function __construct(
        private OrderCommandService $orders,
        private InventoryOutboundContract $inventory,
        private MallPointsTccService $pointsTcc,
        private TccCoordinatorClient $tccClient,
    ) {}

    /**
     * @return array{closed: int, errors: int}
     */
    public function sweepExpired(): array
    {
        $timeoutMs = (int) config('mall_agg.orders.pending_payment_timeout_ms', 1_800_000);
        if ($timeoutMs < 1) {
            $timeoutMs = 1_800_000;
        }
        $now = MallOrder::nowMillis();

        $closed = 0;
        $errors = 0;

        $query = MallOrder::query()
            ->where('status', MallOrderStatus::Pending)
            ->where(function ($q) use ($now, $timeoutMs) {
                $q->where(function ($q2) use ($now, $timeoutMs) {
                    $q2->whereIn('checkout_phase', [
                        CheckoutPhase::None->value,
                        CheckoutPhase::OrderCreated->value,
                    ])->whereRaw('ct + ? < ?', [$timeoutMs, $now]);
                })->orWhere(function ($q2) use ($now, $timeoutMs) {
                    $q2->whereIn('checkout_phase', [
                        CheckoutPhase::PointsTryPending->value,
                        CheckoutPhase::AwaitPayment->value,
                    ])->whereRaw('ut + ? < ?', [$timeoutMs, $now]);
                });
            })
            ->orderBy('id');

        $query->chunkById(50, function ($orders) use (&$closed, &$errors): void {
            foreach ($orders as $order) {
                if (! $this->cancelStalePendingOrder($order)) {
                    $errors++;
                } else {
                    $closed++;
                }
            }
        });

        Log::info('[mall-sweep] overdue sweep done', ['closed' => $closed, 'errors' => $errors]);

        return ['closed' => $closed, 'errors' => $errors];
    }

    /**
     * @return bool true if this order was cancelled by this call or already non-pending
     */
    public function cancelStalePendingOrder(MallOrder $order): bool
    {
        $order->refresh();
        if ($order->status !== MallOrderStatus::Pending) {
            return true;
        }

        $coordIdem = $order->tcc_idem_key;
        if ($coordIdem !== null && $coordIdem > 0) {
            try {
                $this->tccClient->cancel((string) $coordIdem, TccCancelReason::OrderClosed);
            } catch (Throwable $e) {
                Log::warning('[mall-sweep] TCC cancel failed', ['order_id' => $order->id, 'message' => $e->getMessage()]);
            }
        }

        $extId = $order->ext_inventory ? trim($order->ext_id) : '';

        try {
            $this->orders->transitionStatus($order, MallOrderStatus::Cancelled, false);
        } catch (Throwable $e) {
            Log::warning('[mall-sweep] transition failed', ['order_id' => $order->id, 'message' => $e->getMessage()]);

            return false;
        }

        if ($order->ext_inventory && $extId !== '') {
            try {
                $this->inventory->release($extId);
            } catch (Throwable $e) {
                Log::warning('[mall-sweep] inventory release failed', ['order_id' => $order->id, 'message' => $e->getMessage()]);
            }
        }

        $holds = PointsFlow::query()
            ->where('oid', $order->id)
            ->where('state', PointsHoldState::TrySucceeded)
            ->whereNotNull('tcc_idem_key')
            ->get();

        foreach ($holds as $hold) {
            $key = (string) $hold->tcc_idem_key;
            if ($key === '') {
                continue;
            }
            try {
                $this->pointsTcc->cancel($key);
            } catch (Throwable $e) {
                Log::warning('[mall-sweep] points cancel failed', ['order_id' => $order->id, 'message' => $e->getMessage()]);
            }
        }

        return true;
    }
}
