<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Components\ApiResponse;
use App\Enums\CheckoutPhase;
use App\Exceptions\FoundationAuthRequiredException;
use App\Http\Controllers\Controller;
use App\Models\MallOrder;
use App\Services\mall\CheckoutOrchestrator;
use App\Services\mall\FoundationUser;
use App\Services\user\UserFoundationGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class MallCheckoutController extends Controller
{
    public function __construct(
        private readonly UserFoundationGateway $foundationGateway,
        private readonly CheckoutOrchestrator $checkout,
    ) {}

    public function store(Request $request): JsonResponse
    {
        try {
            $user = $this->requireAuthenticatedUser($request);
        } catch (FoundationAuthRequiredException $e) {
            return $this->unauthorizedResponse($e);
        }

        $validator = Validator::make($request->all(), [
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|integer|min:1',
            'lines.*.quantity' => 'required|integer|min:1',
            'points_minor' => 'sometimes|integer|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(ApiResponse::error(100, $validator->errors()->first()), 422);
        }

        /** @var list<array{product_id: int, quantity: int}> $lines */
        $lines = [];
        foreach ($request->input('lines', []) as $line) {
            if (! is_array($line)) {
                continue;
            }
            $lines[] = [
                'product_id' => (int) $line['product_id'],
                'quantity' => (int) $line['quantity'],
            ];
        }

        $pointsMinor = (int) $request->input('points_minor', 0);

        try {
            $result = $this->checkout->checkout(FoundationUser::id($user), $lines, $pointsMinor);
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error(40001, $e->getMessage()), 422);
        }

        $order = $result['order'];
        $this->logHandledApiRequest($request, ['handler' => 'mall.checkout.store', 'order_id' => $order->id]);

        return response()->json(ApiResponse::ok([
            'order' => $this->serializeOrder($order),
            'prepay' => $result['prepay'],
            'points_tcc_idem_key' => $result['points_tcc_idem_key'],
            'tid' => $result['tid'],
        ]), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireAuthenticatedUser(Request $request): array
    {
        $token = trim((string) $request->header('X-User-Access-Token', ''));
        if ($token === '') {
            throw new FoundationAuthRequiredException(
                'Authentication required. Send header: X-User-Access-Token: <access_token> (raw JWT, no Bearer prefix).'
            );
        }

        return $this->foundationGateway->fetchCurrentUser($request);
    }

    private function unauthorizedResponse(FoundationAuthRequiredException $e): JsonResponse
    {
        return response()->json(
            ApiResponse::error(
                (int) config('mall_agg.foundation.unauthorized_code', 40101),
                $e->getMessage()
            ),
            401
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(MallOrder $order): array
    {
        $order->loadMissing('items');

        $lines = [];
        foreach ($order->items as $item) {
            $lines[] = [
                'pid' => (int) $item->pid,
                'quantity' => (int) $item->quantity,
                'unit_price' => (int) $item->unit_price,
            ];
        }

        return [
            'id' => (int) $order->id,
            'uid' => (int) $order->uid,
            'status' => $order->status->value,
            'total_price' => (int) $order->total_price,
            'ct' => (int) $order->ct,
            'ut' => (int) $order->ut,
            'lines' => $lines,
            'ext_inventory' => (bool) $order->ext_inventory,
            'checkout_phase' => $order->checkout_phase?->value ?? CheckoutPhase::None->value,
            'tid' => $order->tid,
        ];
    }
}
