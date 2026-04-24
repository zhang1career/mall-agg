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
 * Payment gateway success path: commit inventory hold, TCC confirm, mark order paid.
 */
final readonly class MallPaymentCallbackService
{
    public function __construct(
        private OrderCommandService $orders,
        private InternalOrderParticipantService $orderParticipant,
        private InternalInventoryParticipantService $inventoryParticipant,
        private TccCoordinatorClient $tccClient,
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

        $coordIdem = $order->tcc_idem_key;
        if ($coordIdem === null || $coordIdem < 1) {
            throw new RuntimeException('Order is missing tcc_idem_key; cannot confirm TCC transaction.');
        }
        $this->tccClient->confirm((string) $coordIdem);

        $order = $this->orderParticipant->confirmPaid($orderId);
        $order->checkout_phase = CheckoutPhase::Completed;
        $order->save();

        return $order->fresh(['items']) ?? $order;
    }
}
