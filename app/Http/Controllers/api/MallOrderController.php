<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Enums\CheckoutPhase;
use App\Enums\MallOrderStatus;
use App\Exceptions\ConfigurationMissingException;
use App\Exceptions\FoundationAuthRequiredException;
use App\Http\Controllers\Controller;
use App\Models\MallOrder;
use App\Services\mall\FoundationUser;
use App\Services\mall\OrderCommandService;
use App\Services\user\UserFoundationGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Paganini\Constants\ResponseConstant;

class MallOrderController extends Controller
{
    public function __construct(
        private readonly UserFoundationGateway $foundationGateway,
        private readonly OrderCommandService $orders) {}

    /**
     * @throws FoundationAuthRequiredException
     * @throws ConfigurationMissingException
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->requireAuthenticatedUser($request);

        $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|integer|min:1',
            'lines.*.quantity' => 'required|integer|min:1',
        ]);

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

        $order = $this->orders->createDraftPendingOrder(FoundationUser::id($user), $lines);

        $this->logHandledApiRequest($request, ['handler' => 'mall.orders.store', 'order_id' => $order->id]);

        return response()->json(ApiResponse::ok($this->serializeOrder($order)), 201);
    }

    /**
     * @throws FoundationAuthRequiredException
     * @throws ConfigurationMissingException
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $this->requireAuthenticatedUser($request);

        $request->validate([
            'status' => 'required',
        ]);

        $raw = $request->input('status');
        if (! is_string($raw) && ! is_int($raw)) {
            return response()->json(ApiResponse::error(ResponseConstant::RET_INVALID_PARAM, 'Invalid status.'), 422);
        }

        $next = MallOrderStatus::fromClient($raw);

        $order = $this->orders->findForUser($id, FoundationUser::id($user));
        $order = $this->orders->transitionStatus($order, $next);

        $this->logHandledApiRequest($request, ['handler' => 'mall.orders.update', 'order_id' => $id]);

        return response()->json(ApiResponse::ok($this->serializeOrder($order)));
    }

    /**
     * @throws FoundationAuthRequiredException
     * @throws ConfigurationMissingException
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->requireAuthenticatedUser($request);

        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        $paginator = $this->orders->paginateForUser(FoundationUser::id($user), $perPage);
        $items = [];
        foreach ($paginator->items() as $order) {
            $items[] = $this->serializeOrderSummary($order);
        }

        $this->logHandledApiRequest($request, ['handler' => 'mall.orders.index']);

        return response()->json(ApiResponse::ok([
            'items' => $items,
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]));
    }

    /**
     * @throws FoundationAuthRequiredException
     * @throws ConfigurationMissingException
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->requireAuthenticatedUser($request);

        $order = $this->orders->findForUser($id, FoundationUser::id($user));

        $this->logHandledApiRequest($request, ['handler' => 'mall.orders.show', 'order_id' => $id]);

        return response()->json(ApiResponse::ok($this->serializeOrder($order)));
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
            'ext_id' => $order->ext_id,
            'checkout_phase' => $order->checkout_phase?->value ?? CheckoutPhase::None->value,
            'tid' => $order->tid,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrderSummary(MallOrder $order): array
    {
        return [
            'id' => $order->id,
            'uid' => $order->uid,
            'status' => $order->status->value,
            'total_price' => $order->total_price,
            'ct' => $order->ct,
            'ut' => $order->ut,
        ];
    }
}
