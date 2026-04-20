<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Contracts\InventoryOutboundContract;
use App\Contracts\PaymentOutboundContract;
use App\Enums\CheckoutPhase;
use App\Enums\MallOrderStatus;
use App\Models\MallOrder;
use App\Services\Transaction\TccCoordinatorClient;
use RuntimeException;
use Throwable;

final readonly class CheckoutOrchestrator
{
    public function __construct(
        private OrderCommandService $orders,
        private InventoryOutboundContract $inventory,
        private PaymentOutboundContract $payment,
        private MallPointsTccService $pointsTcc,
        private TccCoordinatorClient $tccClient,
    ) {}

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lines
     * @return array{order: MallOrder, prepay: array<string, mixed>, points_tcc_idem_key: string|null, tid: string}
     */
    public function checkout(int $uid, array $lines, int $pointsMinor = 0): array
    {
        if (! (bool) config('mall_agg.checkout.use_coordinators', false)) {
            throw new RuntimeException('Coordinator checkout is disabled. Use POST /api/mall/orders or enable MALL_CHECKOUT_USE_COORDINATORS.');
        }

        $useTccCoordinator = (bool) config('mall_agg.checkout.use_tcc_coordinator', false);
        $branchMetaPoints = (int) config('mall_agg.tcc.branch_meta_points_id', 0);

        $reserveId = null;
        $order = null;
        $pointsTccIdemKey = null;
        $globalTxId = null;

        try {
            $reserved = $this->inventory->reserve($uid, $lines);
            $reserveId = $reserved['reserve_id'];

            $order = $this->orders->createOrderWithExternalInventoryReserved($uid, $lines, $reserveId, null);

            if ($pointsMinor > 0) {
                $pointsTccIdemKey = 'ord:'.$order->id.':'.bin2hex(random_bytes(8));
                if ($useTccCoordinator) {
                    if ($branchMetaPoints < 1) {
                        throw new RuntimeException('mall_agg.tcc.branch_meta_points_id is not configured.');
                    }
                    $detail = $this->tccClient->begin([
                        'branches' => [
                            [
                                'branch_meta_id' => $branchMetaPoints,
                                'payload' => [
                                    'uid' => $uid,
                                    'order_id' => $order->id,
                                    'amount_minor' => $pointsMinor,
                                    'tcc_idem_key' => $pointsTccIdemKey,
                                ],
                            ],
                        ],
                        'auto_confirm' => false,
                        'context' => [
                            'order_id' => $order->id,
                            'uid' => $uid,
                        ],
                    ]);
                    $globalTxId = (string) ($detail['global_tx_id'] ?? '');
                    $idemKey = (int) ($detail['idem_key'] ?? 0);
                    $order->tid = $globalTxId !== '' ? $globalTxId : '';
                    $order->tcc_idem_key = $idemKey !== 0 ? $idemKey : null;
                } else {
                    $this->pointsTcc->tryFreeze($uid, $pointsMinor, $order->id, $pointsTccIdemKey);
                }
                $order->checkout_phase = CheckoutPhase::PointsTryPending;
                $order->save();
            }

            $prepay = $this->payment->createPrepay($order->id, $order->total_price, $uid);
            $order->checkout_phase = CheckoutPhase::AwaitPayment;
            $order->save();

            return [
                'order' => $order->fresh(['items']),
                'prepay' => $prepay,
                'points_tcc_idem_key' => $pointsTccIdemKey,
                'tid' => $order->tid,
            ];
        } catch (Throwable $e) {
            if ($globalTxId !== null && $globalTxId !== '') {
                try {
                    $this->tccClient->cancel($globalTxId);
                } catch (Throwable) {
                }
            }
            if ($order !== null) {
                try {
                    $this->orders->transitionStatus($order, MallOrderStatus::Cancelled, false);
                } catch (Throwable) {
                }
            }
            if ($pointsTccIdemKey !== null && ! $useTccCoordinator) {
                try {
                    $this->pointsTcc->cancel($pointsTccIdemKey);
                } catch (Throwable) {
                }
            }
            if ($reserveId !== null) {
                try {
                    $this->inventory->release($reserveId);
                } catch (Throwable) {
                }
            }

            throw $e;
        }
    }
}
