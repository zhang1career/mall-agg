<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Enums\CheckoutPhase;
use App\Exceptions\ConfigurationMissingException;
use App\Exceptions\FoundationAuthRequiredException;
use App\Http\Controllers\Controller;
use App\Models\MallOrder;
use App\Services\mall\CheckoutOrchestrator;
use App\Services\mall\FoundationUser;
use App\Services\mall\OrderCommandService;
use App\Services\user\UserFoundationGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MallCheckoutController extends Controller
{
    public function __construct(
        private readonly UserFoundationGateway $foundationGateway,
        private readonly OrderCommandService $orders,
        private readonly CheckoutOrchestrator $checkout) {}

    /**
     * @throws FoundationAuthRequiredException
     * @throws ConfigurationMissingException
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->requireAuthenticatedUser($request);

        $validated = $request->validate([
            'order_id' => 'required|integer|min:1',
            'points_minor' => 'sometimes|integer|min:0',
        ]);

        $orderId = (int) $validated['order_id'];
        $pointsMinor = (int) ($validated['points_minor'] ?? 0);
        $uid = FoundationUser::id($user);

        $order = $this->orders->findForUser($orderId, $uid);

        $xRequestId = trim((string) $request->header('X-Request-Id', ''));
        if ($xRequestId === '') {
            $xRequestId = '0';
        }

        $result = $this->checkout->checkoutExistingOrder($uid, $order, $pointsMinor, $xRequestId);

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
     *
     * @throws FoundationAuthRequiredException
     * @throws ConfigurationMissingException
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

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(MallOrder $order): array
    {
        $order->loadMissing('items');

        $lines = [];
        foreach ($order->items as $item) {
            $lines[] = [
                'pid' => $item->pid,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ];
        }

        return [
            'id' => $order->id,
            'uid' => $order->uid,
            'status' => $order->status->value,
            'total_price' => $order->total_price,
            'points_deduct_minor' => $order->points_deduct_minor,
            'cash_payable_minor' => $order->cash_payable_minor,
            'ct' => $order->ct,
            'ut' => $order->ut,
            'lines' => $lines,
            'ext_inventory' => $order->ext_inventory,
            'checkout_phase' => $order->checkout_phase?->value ?? CheckoutPhase::None->value,
            'tid' => $order->tid,
        ];
    }
}
