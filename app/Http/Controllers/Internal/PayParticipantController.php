<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\mall\Internal\InternalPayParticipantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class PayParticipantController extends Controller
{
    public function __construct(
        private readonly InternalPayParticipantService $pay,
    ) {}

    public function action(Request $request): JsonResponse
    {
        $data = $this->sagaParticipantData($request);
        $orderId = (int) ($data['order_id'] ?? 0);
        $idem = (string) ($data['saga_step_idem_key'] ?? '');

        if ($orderId < 1) {
            return response()->json(ApiResponse::error(100, 'Invalid order_id.'), 200);
        }

        try {
            $out = $this->pay->actionPhase($orderId, $idem);
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error(100, $e->getMessage()), 200);
        }

        return response()->json(ApiResponse::ok($out));
    }

    /**
     * TCC coordinator Try for branch {@code prepay} (same prepay body as {@see action}, different idempotency field).
     */
    public function try(Request $request): JsonResponse
    {
        $data = $this->payTryPayload($request);
        $orderId = (int) ($data['order_id'] ?? 0);
        $idem = trim((string) $request->input('idempotency_key', ''));

        if ($orderId < 1) {
            return response()->json(ApiResponse::error(100, 'Invalid order_id.'), 200);
        }

        try {
            $out = $this->pay->tryPhase($orderId, $idem);
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error(100, $e->getMessage()), 200);
        }

        return response()->json(ApiResponse::ok($out));
    }

    public function compensate(Request $request): JsonResponse
    {
        $data = $this->sagaParticipantData($request);
        $orderId = (int) ($data['order_id'] ?? 0);
        $idem = (string) ($data['saga_step_idem_key'] ?? '');

        if ($orderId < 1) {
            return response()->json(ApiResponse::error(100, 'Invalid order_id.'), 200);
        }

        try {
            $this->pay->compensatePhase($orderId, $idem);
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error(100, $e->getMessage()), 200);
        }

        return response()->json(ApiResponse::ok(['order_id' => $orderId]));
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

        return is_array($all) ? $all : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function sagaParticipantData(Request $request): array
    {
        $data = $this->payload($request);
        $ctx = $request->input('context');
        if (is_array($ctx) && ($data['order_id'] ?? 0) < 1 && isset($ctx['order_id'])) {
            $data['order_id'] = (int) $ctx['order_id'];
        }
        if (trim((string) ($data['saga_step_idem_key'] ?? '')) === '') {
            $sid = trim((string) $request->input('saga_instance_id', ''));
            $step = trim((string) $request->input('step_index', ''));
            if ($sid !== '' && $step !== '') {
                $data['saga_step_idem_key'] = $sid.':'.$step;
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function payTryPayload(Request $request): array
    {
        $data = $this->payload($request);
        $ctx = $request->input('context');
        if (is_array($ctx) && ($data['order_id'] ?? 0) < 1 && isset($ctx['order_id'])) {
            $data['order_id'] = (int) $ctx['order_id'];
        }

        return $data;
    }
}
