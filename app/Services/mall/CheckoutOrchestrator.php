<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\CheckoutPhase;
use App\Enums\MallOrderStatus;
use App\Models\MallOrder;
use App\Services\Transaction\SagaCoordinatorClient;
use RuntimeException;

/**
 * Step 2: POST /api/mall/checkout — saga start drives inventory, order bind, TCC (points + prepay), then assembles client prepay.
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
                'TCC is not configured for checkout. Set MALL_TCC_FLOW_ID and MALL_TCC_ACCESS_KEY.'
            );
        }

        $stepCodes = config('mall_agg.saga.checkout_steps');
        $branchCodes = config('mall_agg.tcc.checkout_branches');
        if (! is_array($stepCodes) || ! is_array($branchCodes)) {
            throw new RuntimeException('mall_agg saga.checkout_steps / tcc.checkout_branches must be configured.');
        }
        $inventoryStep = (string) ($stepCodes['inventory'] ?? '');
        $orderStep = (string) ($stepCodes['order'] ?? '');
        $payStep = (string) ($stepCodes['pay'] ?? '');
        $tryPointsBranch = (string) ($branchCodes['try_points'] ?? '');
        $prepayBranch = (string) ($branchCodes['prepay'] ?? '');
        if ($inventoryStep === '' || $orderStep === '' || $payStep === '' || $tryPointsBranch === '' || $prepayBranch === '') {
            throw new RuntimeException('Checkout step or TCC branch code resolved empty; check mall_agg config.');
        }

        $lines = $this->orders->linesFromOrderItems($order);

        $sagaData = $this->sagaCoordinator->start([
            'access_key' => $accessKey,
            'flow_id' => $flowId,
            'tcc_access_key' => $tccAccessKey,
            'context' => [
                'uid' => $uid,
                'order_id' => $order->id,
                'lines' => $lines,
                'points_minor' => $pointsMinor,
            ],
            'step_payloads' => (object) [
                $inventoryStep => [
                    'uid' => $uid,
                    'lines' => $lines,
                ],
                $orderStep => [
                    'uid' => $uid,
                    'order_id' => $order->id,
                ],
                $payStep => [
                    'biz_id' => $tccFlowId,
                    'auto_confirm' => false,
                    'branches' => [
                        [
                            'branch_code' => $tryPointsBranch,
                            'payload' => [
                                'uid' => $uid,
                                'order_id' => $order->id,
                                'amount_minor' => $pointsMinor,
                            ],
                        ],
                        [
                            'branch_code' => $prepayBranch,
                            'payload' => [
                                'order_id' => $order->id,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $ctx = $sagaData['context'] ?? null;
        if (! is_array($ctx)) {
            throw new RuntimeException('Saga start response missing context; check coordinator envelope and flow.');
        }

        $needConfirm = $sagaData['need_confirm'] ?? null;
        if (! is_array($needConfirm) || $needConfirm === []) {
            throw new RuntimeException(
                'Saga checkout response must include non-empty need_confirm (pay step is_need_confirm).'
            );
        }
        $partial = $this->prepayPartialFromNeedConfirm($needConfirm, $prepayBranch);
        if ($partial === null || $partial === []) {
            throw new RuntimeException(
                'Saga checkout need_confirm does not contain prepay; check TCC branch "'
                .$prepayBranch.'" participant returns data.prepay.'
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

        $prepay = $this->assembleCheckoutPrepay($partial, $order, $uid);

        return [
            'order' => $order->fresh(['items']),
            'prepay' => $prepay,
            'points_tcc_idem_key' => $pointsKeyStr,
            'tid' => trim((string) $order->tid),
        ];
    }

    /**
     * @param  list<mixed>  $needConfirm
     * @return array<string, mixed>|null
     */
    private function prepayPartialFromNeedConfirm(array $needConfirm, string $prepayBranchCode): ?array
    {
        foreach (array_reverse($needConfirm) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $response = $item['response'] ?? null;
            if (! is_array($response)) {
                continue;
            }
            $extracted = $this->extractPrepayFromCoordinatorResponse($response, $prepayBranchCode);
            if ($extracted !== null && $extracted !== []) {
                return $extracted;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractPrepayFromCoordinatorResponse(array $response, string $prepayBranchCode): ?array
    {
        if (isset($response['prepay']) && is_array($response['prepay'])) {
            return $response['prepay'];
        }
        $branches = $response['branches'] ?? null;
        if (! is_array($branches)) {
            return null;
        }
        $entry = $branches[$prepayBranchCode] ?? null;
        if (! is_array($entry)) {
            return null;
        }
        $participantJson = $entry['data'] ?? null;
        if (! is_array($participantJson)) {
            return null;
        }
        $biz = $participantJson['data'] ?? null;
        if (is_array($biz) && isset($biz['prepay']) && is_array($biz['prepay'])) {
            return $biz['prepay'];
        }
        if (isset($participantJson['prepay']) && is_array($participantJson['prepay'])) {
            return $participantJson['prepay'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $partial
     * @return array<string, mixed>
     */
    private function assembleCheckoutPrepay(array $partial, MallOrder $order, int $uid): array
    {
        $order->refresh();
        $cash = (int) $order->cash_payable_minor;
        if ($cash < 1) {
            $cash = max(0, (int) $order->total_price - (int) $order->points_deduct_minor);
        }

        $envelope = [
            'schema_version' => '1',
            'pay_channel' => 'stub',
            'order_id' => (int) $order->id,
            'uid' => $uid,
            'amount_minor' => $cash,
            'invoke_payment' => 'placeholder',
        ];

        return array_merge($envelope, $partial);
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
