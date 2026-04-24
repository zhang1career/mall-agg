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
        $data = $this->payload($request);
        $uid = (int) ($data['uid'] ?? 0);
        $inventoryToken = (string) ($data['inventory_token'] ?? '');
        $idem = (string) ($data['saga_step_idem_key'] ?? '');
        $sagaIdemKey = isset($data['saga_idem_key']) ? (int) $data['saga_idem_key'] : null;
        if ($sagaIdemKey !== null && $sagaIdemKey < 1) {
            $sagaIdemKey = null;
        }

        try {
            $lines = $this->linesFromPayload($data);
            $out = $this->orders->actionPhase($uid, $lines, $inventoryToken, $idem, $sagaIdemKey);
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
     * @param  array<string, mixed>  $data
     * @return list<array{product_id: int, quantity: int}>
     */
    private function linesFromPayload(array $data): array
    {
        $raw = $data['lines'] ?? null;
        if (! is_array($raw)) {
            throw new RuntimeException('lines must be an array.');
        }

        $lines = [];
        foreach ($raw as $line) {
            if (! is_array($line)) {
                continue;
            }
            $lines[] = [
                'product_id' => (int) ($line['product_id'] ?? 0),
                'quantity' => (int) ($line['quantity'] ?? 0),
            ];
        }

        if ($lines === []) {
            throw new RuntimeException('lines must contain at least one line.');
        }

        return $lines;
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
}
