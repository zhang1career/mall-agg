<?php

declare(strict_types=1);

namespace App\Http\Controllers\internal;

use App\Components\ApiResponse;
use App\Enums\CheckoutPhase;
use App\Http\Controllers\Controller;
use App\Models\MallOrder;
use App\Services\mall\MallPointsTccService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Paganini\Constants\ResponseConstant;

/**
 * Points TCC participant (Try / Confirm / Cancel); routed as `POST /internal/points/*`.
 */
final class TccPointsParticipantController extends Controller
{
    public function __construct(
        private readonly MallPointsTccService $points,
    ) {}

    public function try(Request $request): JsonResponse
    {
        $data = $this->tryPayload($request);
        $uid = (int) ($data['uid'] ?? 0);
        $amountMinor = (int) ($data['amount_minor'] ?? 0);
        $orderId = isset($data['order_id']) ? (int) $data['order_id'] : null;
        $tccIdemKey = $this->resolveBranchIdem($request, $data);
        if ($uid < 1 || $amountMinor < 0 || $tccIdemKey === '') {
            return response()->json(ApiResponse::error(ResponseConstant::RET_INVALID_PARAM, 'Invalid TCC try payload.'));
        }

        if ($amountMinor > 0) {
            $this->points->tryFreeze($uid, $amountMinor, $orderId, $tccIdemKey);
        }

        if ($orderId !== null && $orderId > 0) {
            $ord = MallOrder::query()->find($orderId);
            if ($ord !== null) {
                $ord->points_deduct_minor = $amountMinor;
                $ord->cash_payable_minor = $ord->total_price - $amountMinor;
                $ord->checkout_phase = CheckoutPhase::PointsTryPending;
                $ord->save();
            }
        }

        return response()->json(ApiResponse::ok([]));
    }

    public function confirm(Request $request): JsonResponse
    {
        $data = $this->tccPhasePayload($request);
        $tccIdemKey = $this->resolveBranchIdem($request, $data);
        if ($tccIdemKey === '') {
            return response()->json(ApiResponse::error(ResponseConstant::RET_MISSING_PARAM, 'X-Request-Id or branch idempotency key required.'));
        }

        $this->points->confirm($tccIdemKey);

        return response()->json(ApiResponse::ok([]));
    }

    public function cancel(Request $request): JsonResponse
    {
        $data = $this->tccPhasePayload($request);
        $tccIdemKey = $this->resolveBranchIdem($request, $data);
        if ($tccIdemKey === '') {
            return response()->json(ApiResponse::error(ResponseConstant::RET_MISSING_PARAM, 'X-Request-Id or branch idempotency key required.'));
        }

        $this->points->cancel($tccIdemKey);

        return response()->json(ApiResponse::ok([]));
    }

    /**
     * Branch idempotency: prefer {@code X-Request-Id}, else {@code idempotency_key}, else legacy body {@code tcc_idem_key}.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveBranchIdem(Request $request, array $data): string
    {
        $xr = trim((string) ($request->header('X-Request-Id') ?? ''));
        if ($xr !== '') {
            return $xr;
        }
        $idem = $request->input('idempotency_key');
        if (is_string($idem) && trim($idem) !== '') {
            return trim($idem);
        }

        $key = $data['tcc_idem_key'] ?? '';

        return is_string($key) ? trim($key) : trim((string) $key);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $payload = $request->input('payload');
        if (is_array($payload)) {
            return $payload;
        }

        $all = $request->all();
        if ($all !== []) {
            return $all;
        }

        return [];
    }

    /**
     * Coordinator sends branch facts on the envelope; biz fields may only exist in saga context.
     *
     * @return array<string, mixed>
     */
    private function tryPayload(Request $request): array
    {
        $data = $this->payload($request);
        $ctx = $request->input('context');
        if (is_array($ctx)) {
            if (($data['uid'] ?? 0) < 1 && isset($ctx['uid'])) {
                $data['uid'] = (int) $ctx['uid'];
            }
            if (($data['order_id'] ?? 0) < 1 && isset($ctx['order_id'])) {
                $data['order_id'] = (int) $ctx['order_id'];
            }
            if (($data['amount_minor'] ?? 0) < 1 && isset($ctx['points_minor'])) {
                $data['amount_minor'] = (int) $ctx['points_minor'];
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function tccPhasePayload(Request $request): array
    {
        return $this->payload($request);
    }
}
