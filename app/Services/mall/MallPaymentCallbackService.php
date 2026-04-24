<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\CheckoutPhase;
use App\Enums\MallOrderStatus;
use App\Models\MallOrder;
use App\Services\mall\Internal\InternalInventoryParticipantService;
use App\Services\mall\Internal\InternalOrderParticipantService;
use App\Services\Transaction\TccCoordinatorClient;
use RuntimeException;

/**
 * Payment gateway success path: commit inventory hold, confirm points (TCC or local), mark order paid.
 */
final readonly class MallPaymentCallbackService
{
    public function __construct(
        private OrderCommandService $orders,
        private InternalOrderParticipantService $orderParticipant,
        private InternalInventoryParticipantService $inventoryParticipant,
        private TccCoordinatorClient $tccClient,
        private MallPointsTccService $pointsTcc,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handlePaidNotification(int $orderId, array $payload): MallOrder
    {
        if ($orderId < 1) {
            throw new RuntimeException('Invalid order_id.');
        }

        $order = $this->orders->findById($orderId);

        if ($order->status === MallOrderStatus::Paid) {
            return $order;
        }

        if ($order->status !== MallOrderStatus::Pending) {
            throw new RuntimeException('Order is not pending; cannot apply payment.');
        }

        if ($order->checkout_phase !== CheckoutPhase::AwaitPayment) {
            throw new RuntimeException(
                'TCC confirm and payment finalization are only allowed in AwaitPayment phase (after successful prepay).'
            );
        }

        $tid = trim($order->tid);
        $globalTxInPayload = isset($payload['global_tx_id']) ? trim((string) $payload['global_tx_id']) : '';
        if ($globalTxInPayload !== '' && $tid !== '' && $globalTxInPayload !== $tid) {
            throw new RuntimeException('global_tx_id does not match order tid.');
        }

        $invToken = trim($order->ext_id);
        if ($invToken !== '') {
            $this->inventoryParticipant->confirmPhase($invToken);
        }

        if ((bool) config('mall_agg.checkout.use_tcc_coordinator', false) && $tid !== '') {
            $this->tccClient->confirm($tid);
        } elseif ((int) $order->points_deduct_minor > 0) {
            $pk = isset($payload['points_tcc_idem_key']) ? trim((string) $payload['points_tcc_idem_key']) : '';
            if ($pk === '') {
                throw new RuntimeException('points_tcc_idem_key is required when confirming local points deduction.');
            }
            $this->pointsTcc->confirm($pk);
        }

        $order = $this->orderParticipant->confirmPaid($orderId);
        $order->checkout_phase = CheckoutPhase::Completed;
        $order->save();

        return $order->fresh(['items']) ?? $order;
    }
}
