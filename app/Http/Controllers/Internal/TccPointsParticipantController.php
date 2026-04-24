<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Components\ApiResponse;
use App\Enums\CheckoutPhase;
use App\Http\Controllers\Controller;
use App\Models\MallOrder;
use App\Services\mall\MallPointsTccService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

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
        $tccIdemKey = $this->tccIdemKeyFromPayload($data);
        if ($uid < 1 || $amountMinor < 1 || $tccIdemKey === '') {
            return response()->json(ApiResponse::error(100, 'Invalid TCC try payload.'), 200);
        }

        try {
            $this->points->tryFreeze($uid, $amountMinor, $orderId, $tccIdemKey);
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error(100, $e->getMessage()), 200);
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

        return response()->json(ApiResponse::ok(['tcc_idem_key' => $tccIdemKey]));
    }

    public function confirm(Request $request): JsonResponse
    {
        $data = $this->tccPhasePayload($request);
        $tccIdemKey = $this->tccIdemKeyFromPayload($data);
        if ($tccIdemKey === '') {
            return response()->json(ApiResponse::error(100, 'Missing tcc_idem_key.'), 200);
        }

        try {
            $this->points->confirm($tccIdemKey);
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error(100, $e->getMessage()), 200);
        }

        return response()->json(ApiResponse::ok(['tcc_idem_key' => $tccIdemKey]));
    }

    public function cancel(Request $request): JsonResponse
    {
        $data = $this->tccPhasePayload($request);
        $tccIdemKey = $this->tccIdemKeyFromPayload($data);
        if ($tccIdemKey === '') {
            return response()->json(ApiResponse::error(100, 'Missing tcc_idem_key.'), 200);
        }

        try {
            $this->points->cancel($tccIdemKey);
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error(100, $e->getMessage()), 200);
        }

        return response()->json(ApiResponse::ok(['tcc_idem_key' => $tccIdemKey]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function tccIdemKeyFromPayload(array $data): string
    {
        $key = $data['tcc_idem_key'] ?? '';

        return is_string($key) ? $key : (string) $key;
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
        if ($this->tccIdemKeyFromPayload($data) === '') {
            $idem = $request->input('idempotency_key');
            if (is_string($idem) && $idem !== '') {
                $data['tcc_idem_key'] = $idem;
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function tccPhasePayload(Request $request): array
    {
        $data = $this->payload($request);
        if ($this->tccIdemKeyFromPayload($data) === '') {
            $idem = $request->input('idempotency_key');
            if (is_string($idem) && $idem !== '') {
                $data['tcc_idem_key'] = $idem;
            }
        }

        return $data;
    }
}
