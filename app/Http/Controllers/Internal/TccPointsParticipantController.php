<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
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
        $data = $this->payload($request);
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

        return response()->json(ApiResponse::ok(['tcc_idem_key' => $tccIdemKey]));
    }

    public function confirm(Request $request): JsonResponse
    {
        $data = $this->payload($request);
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
        $data = $this->payload($request);
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
}
