<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\mall\MallPaymentCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Paganini\Constants\ResponseConstant;

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
                return response()->json(ApiResponse::error(ResponseConstant::RET_FORBIDDEN, 'Invalid payment callback token.'), 403);
            }
        }

        $request->validate([
            'order_id' => 'required|integer|min:1',
            'status' => 'sometimes|string',
            'global_tx_id' => 'sometimes|string',
            'points_tcc_idem_key' => 'sometimes|string',
        ]);

        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $statusRaw = strtolower((string) ($payload['status'] ?? 'paid'));
        if (! in_array($statusRaw, ['paid', 'success', 'payment.success'], true)) {
            return response()->json(ApiResponse::error(ResponseConstant::RET_INVALID_PARAM, 'Unsupported payment status.'), 422);
        }

        $orderId = (int) $payload['order_id'];

        $order = $this->payments->handlePaidNotification($orderId, $payload);

        $this->logHandledApiRequest($request, ['handler' => 'payment.callback', 'order_id' => $order->id]);

        return response()->json(ApiResponse::ok([
            'order_id' => $order->id,
            'status' => $order->status->value,
        ]));
    }
}
