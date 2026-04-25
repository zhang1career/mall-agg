<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\mall\MallPaymentCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

final class PaymentCallbackController extends Controller
{
    public function __construct(
        private readonly MallPaymentCallbackService $payments,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = trim((string) config('mall_agg.payment.callback_token', ''));
        if ($secret !== '') {
            $sent = trim((string) $request->header('X-Payment-Callback-Token', ''));
            if ($sent !== $secret) {
                return response()->json(ApiResponse::error(40301, 'Invalid payment callback token.'), 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer|min:1',
            'status' => 'sometimes|string',
            'global_tx_id' => 'sometimes|string',
            'points_tcc_idem_key' => 'sometimes|string',
        ]);
        if ($validator->fails()) {
            return response()->json(ApiResponse::error(100, $validator->errors()->first()), 422);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $statusRaw = strtolower((string) ($payload['status'] ?? 'paid'));
        if (! in_array($statusRaw, ['paid', 'success', 'payment.success'], true)) {
            return response()->json(ApiResponse::error(100, 'Unsupported payment status.'), 422);
        }

        $orderId = (int) $payload['order_id'];

        try {
            $order = $this->payments->handlePaidNotification($orderId, $payload);
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error(40001, $e->getMessage()), 422);
        }

        $this->logHandledApiRequest($request, ['handler' => 'payment.callback', 'order_id' => $order->id]);

        return response()->json(ApiResponse::ok([
            'order_id' => $order->id,
            'status' => $order->status->value,
        ]));
    }
}
