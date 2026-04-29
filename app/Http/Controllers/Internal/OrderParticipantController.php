<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\mall\Internal\InternalOrderParticipantService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class OrderParticipantController extends Controller
{
    public function __construct(
        private readonly InternalOrderParticipantService $orders,
    ) {}

    public function action(Request $request): JsonResponse
    {
        $data = $this->sagaParticipantData($request);
        $uid = (int) ($data['uid'] ?? 0);
        $orderId = (int) ($data['order_id'] ?? 0);
        $inventoryToken = (string) ($data['inventory_token'] ?? '');
        $idem = (string) ($data['saga_step_idem_key'] ?? '');
        $sagaIdemKey = (int) ($data['saga_idem_key'] ?? 0);

        try {
            $out = $this->orders->bindDraftOrderAfterInventory(
                $orderId,
                $uid,
                $inventoryToken,
                $idem,
                $sagaIdemKey,
            );
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error(100, $e->getMessage()), 200);
        }

        return response()->json(ApiResponse::ok($out));
    }

    public function compensate(Request $request): JsonResponse
    {
        $data = $this->payload($request);
        $orderId = (int) ($data['order_id'] ?? 0);
        if ($orderId < 1) {
            return response()->json(ApiResponse::error(100, 'Invalid order_id.'), 200);
        }

        try {
            $this->orders->compensate($orderId);
        } catch (ModelNotFoundException) {
            return response()->json(ApiResponse::error(100, 'Order not found.'), 200);
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
     * Saga sends inventory_token in merged context after step 0; saga instance idem: header X-Request-Id (or legacy body idem_key).
     *
     * @return array<string, mixed>
     */
    private function sagaParticipantData(Request $request): array
    {
        $data = $this->payload($request);
        $ctx = $request->input('context');
        if (is_array($ctx)) {
            if (trim((string) ($data['inventory_token'] ?? '')) === '' && isset($ctx['inventory_token'])) {
                $data['inventory_token'] = (string) $ctx['inventory_token'];
            }
        }
        if (($data['saga_idem_key'] ?? 0) < 1) {
            $xr = trim((string) ($request->header('X-Request-Id') ?? ''));
            if ($xr !== '' && ctype_digit($xr)) {
                $data['saga_idem_key'] = (int) $xr;
            }
        }
        if (($data['saga_idem_key'] ?? 0) < 1) {
            $root = $request->input('idem_key');
            if (is_int($root) || is_float($root)) {
                $data['saga_idem_key'] = (int) $root;
            } elseif (is_string($root) && $root !== '' && ctype_digit($root)) {
                $data['saga_idem_key'] = (int) $root;
            }
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
}
