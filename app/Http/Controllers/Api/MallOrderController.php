<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Components\ApiResponse;
use App\Enums\MallOrderStatus;
use App\Exceptions\FoundationAuthRequiredException;
use App\Http\Controllers\Controller;
use App\Models\MallOrder;
use App\Services\Mall\FoundationUser;
use App\Services\Mall\OrderCommandService;
use App\Services\User\UserFoundationGateway;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use ValueError;

class MallOrderController extends Controller
{
    public function __construct(
        private readonly UserFoundationGateway $foundationGateway,
        private readonly OrderCommandService $orders,
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

        try {
            $order = $this->orders->createOrder(FoundationUser::id($user), $lines);
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error(40001, $e->getMessage()), 422);
        }

        $this->logHandledApiRequest($request, ['handler' => 'mall.orders.store', 'order_id' => $order->id]);

        return response()->json(ApiResponse::ok($this->serializeOrder($order)), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = $this->requireAuthenticatedUser($request);
        } catch (FoundationAuthRequiredException $e) {
            return $this->unauthorizedResponse($e);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(ApiResponse::error(100, $validator->errors()->first()), 422);
        }

        $raw = (string) $request->input('status');
        try {
            $next = MallOrderStatus::fromClient($raw);
        } catch (ValueError) {
            return response()->json(ApiResponse::error(100, 'Invalid status.'), 422);
        }

        try {
            $order = $this->orders->findForUser($id, FoundationUser::id($user));
            $order = $this->orders->transitionStatus($order, $next);
        } catch (ModelNotFoundException) {
            return response()->json(ApiResponse::error(40401, 'Order not found.'), 404);
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error(40001, $e->getMessage()), 422);
        }

        $this->logHandledApiRequest($request, ['handler' => 'mall.orders.update', 'order_id' => $id]);

        return response()->json(ApiResponse::ok($this->serializeOrder($order)));
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $this->requireAuthenticatedUser($request);
        } catch (FoundationAuthRequiredException $e) {
            return $this->unauthorizedResponse($e);
        }

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

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = $this->requireAuthenticatedUser($request);
        } catch (FoundationAuthRequiredException $e) {
            return $this->unauthorizedResponse($e);
        }

        try {
            $order = $this->orders->findForUser($id, FoundationUser::id($user));
        } catch (ModelNotFoundException) {
            return response()->json(ApiResponse::error(40401, 'Order not found.'), 404);
        }

        $this->logHandledApiRequest($request, ['handler' => 'mall.orders.show', 'order_id' => $id]);

        return response()->json(ApiResponse::ok($this->serializeOrder($order)));
    }

    /**
     * @return array<string, mixed>
     */
    private function requireAuthenticatedUser(Request $request): array
    {
        $token = $request->bearerToken();
        if ($token === null || trim($token) === '') {
            throw new FoundationAuthRequiredException(
                'Authorization required. Send Authorization: Bearer <access_token>.'
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
                'product_id' => (int) $item->product_id,
                'quantity' => (int) $item->quantity,
                'unit_price_minor' => (int) $item->unit_price_minor,
            ];
        }

        return [
            'id' => (int) $order->id,
            'user_id' => (int) $order->user_id,
            'status' => $order->status->value,
            'total_amount_minor' => (int) $order->total_amount_minor,
            'ct' => (int) $order->ct,
            'ut' => (int) $order->ut,
            'lines' => $lines,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrderSummary(MallOrder $order): array
    {
        return [
            'id' => (int) $order->id,
            'user_id' => (int) $order->user_id,
            'status' => $order->status->value,
            'total_amount_minor' => (int) $order->total_amount_minor,
            'ct' => (int) $order->ct,
            'ut' => (int) $order->ut,
        ];
    }
}
