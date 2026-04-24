<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\CheckoutPhase;
use App\Enums\MallOrderStatus;
use App\Models\MallOrder;
use App\Services\Transaction\SagaCoordinatorClient;
use RuntimeException;

/**
 * Step 2: POST /api/mall/checkout — single path per docs/design/pay_swiming.puml (saga start drives inventory, order bind, TCC, prepay).
 */
final readonly class CheckoutOrchestrator
{
    public function __construct(
        private OrderCommandService $orders,
        private SagaCoordinatorClient $sagaCoordinator,
    ) {}

    /**
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
        if ($order->checkout_phase !== CheckoutPhase::None) {
            throw new RuntimeException('Order is not a draft; checkout already started or completed.');
        }
        if ($pointsMinor < 0 || $pointsMinor > $order->total_price) {
            throw new RuntimeException('Invalid points_minor.');
        }

        $flowId = (int) config('mall_agg.saga.flow_id', 0);
        $accessKey = trim((string) config('mall_agg.saga.access_key', ''));
        if ($flowId < 1 || $accessKey === '') {
            throw new RuntimeException(
                'Saga coordinator is not configured. Set MALL_SAGA_FLOW_ID and MALL_SAGA_ACCESS_KEY.'
            );
        }

        $tccAccessKey = trim((string) config('mall_agg.tcc.access_key', ''));
        $tccFlowId = (int) config('mall_agg.tcc.flow_id', 0);
        if ($tccFlowId < 1 || $tccAccessKey === '') {
            throw new RuntimeException(
                'TCC is not configured for saga context. Set MALL_TCC_FLOW_ID and MALL_TCC_ACCESS_KEY.'
            );
        }

        $lines = $this->orders->linesFromOrderItems($order);

        $sagaData = $this->sagaCoordinator->start([
            'access_key' => $accessKey,
            'flow_id' => $flowId,
            'context' => [
                'uid' => $uid,
                'order_id' => $order->id,
                'lines' => $lines,
                'points_minor' => $pointsMinor,
                'tcc_access_key' => $tccAccessKey,
                'tcc_flow_id' => $tccFlowId,
            ],
            'step_payloads' => (object) [
                '0' => [
                    'uid' => $uid,
                    'lines' => $lines,
                ],
                '1' => [
                    'uid' => $uid,
                    'order_id' => $order->id,
                ],
            ],
        ]);

        $ctx = $sagaData['context'] ?? null;
        if (! is_array($ctx)) {
            throw new RuntimeException('Saga start response missing context; check coordinator envelope and flow.');
        }

        $prepay = $ctx['prepay'] ?? null;
        if (! is_array($prepay) || $prepay === []) {
            throw new RuntimeException(
                'Saga checkout context has no prepay; ensure the pay participant returns data.prepay and the flow reaches that step within the sync budget.'
            );
        }

        $order->refresh();

        $globalTxId = trim((string) ($ctx['global_tx_id'] ?? ''));
        $coordIdem = $this->intFromCoordinatorIdem($ctx['idem_key'] ?? null);
        if ($globalTxId !== '') {
            $order->tid = $globalTxId;
        }
        if ($coordIdem > 0) {
            $order->tcc_idem_key = $coordIdem;
        }
        $order->save();

        $pointsKey = $ctx['tcc_idem_key'] ?? null;
        $pointsKeyStr = is_string($pointsKey) && $pointsKey !== '' ? $pointsKey : null;

        return [
            'order' => $order->fresh(['items']),
            'prepay' => $prepay,
            'points_tcc_idem_key' => $pointsKeyStr,
            'tid' => trim((string) $order->tid),
        ];
    }

    private function intFromCoordinatorIdem(mixed $raw): int
    {
        if (is_int($raw)) {
            return $raw > 0 ? $raw : 0;
        }
        if (is_string($raw) && $raw !== '' && ctype_digit($raw)) {
            return (int) $raw;
        }

        return 0;
    }
}
