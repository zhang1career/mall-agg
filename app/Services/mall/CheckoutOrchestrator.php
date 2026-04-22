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
     * Step 2: existing pending order — points try (optional), then cash prepay for (total_price − points).
     *
     * @return array{order: MallOrder, prepay: array<string, mixed>, points_tcc_idem_key: string|null, tid: string}
     */
    public function checkoutExistingOrder(int $uid, MallOrder $order, int $pointsMinor): array
    {
        if ($order->uid !== $uid) {
            throw new RuntimeException('Order does not belong to the current user.');
        }
        if ($order->status !== MallOrderStatus::Pending) {
            throw new RuntimeException('Order is not pending checkout.');
        }
        if ($pointsMinor < 0 || $pointsMinor > $order->total_price) {
            throw new RuntimeException('Invalid points_minor.');
        }

        $useTccCoordinator = (bool) config('mall_agg.checkout.use_tcc_coordinator', false);
        $branchMetaPoints = (int) config('mall_agg.tcc.branch_meta_points_id', 0);

        if ($order->checkout_phase === CheckoutPhase::AwaitPayment
            && $order->points_deduct_minor === $pointsMinor) {
            $cashPayable = $order->total_price - $pointsMinor;
            $prepay = $this->payment->createPrepay($order->id, $cashPayable, $uid);

            return [
                'order' => $order->fresh(['items']),
                'prepay' => $prepay,
                'points_tcc_idem_key' => null,
                'tid' => $order->tid,
            ];
        }

        $allowedFirstPhases = [CheckoutPhase::None, CheckoutPhase::OrderCreated];
        if (! in_array($order->checkout_phase, $allowedFirstPhases, true)) {
            throw new RuntimeException('Order cannot be checked out in the current phase.');
        }

        $pointsTccIdemKey = null;
        $globalTxId = null;

        try {
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
                $order->points_deduct_minor = $pointsMinor;
                $order->cash_payable_minor = $order->total_price - $pointsMinor;
                $order->checkout_phase = CheckoutPhase::PointsTryPending;
                $order->save();
            } else {
                $order->points_deduct_minor = 0;
                $order->cash_payable_minor = $order->total_price;
                $order->save();
            }

            $prepay = $this->payment->createPrepay($order->id, $order->cash_payable_minor, $uid);
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
            if ($order->status === MallOrderStatus::Pending) {
                $extId = $order->ext_inventory ? trim($order->ext_id) : '';
                $restoreLocal = ! $order->ext_inventory;
                try {
                    $this->orders->transitionStatus($order, MallOrderStatus::Cancelled, $restoreLocal);
                } catch (Throwable) {
                }
                if ($order->ext_inventory && $extId !== '') {
                    try {
                        $this->inventory->release($extId);
                    } catch (Throwable) {
                    }
                }
            }
            if ($pointsTccIdemKey !== null && ! $useTccCoordinator) {
                try {
                    $this->pointsTcc->cancel($pointsTccIdemKey);
                } catch (Throwable) {
                }
            }

            throw $e;
        }
    }
}
