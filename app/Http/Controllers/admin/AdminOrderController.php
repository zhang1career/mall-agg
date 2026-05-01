<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Enums\MallOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\MallOrder;
use App\Services\mall\OrderCommandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use ValueError;

class AdminOrderController extends Controller
{
    public function __construct(
        private readonly OrderCommandService $orders,
    ) {}

    public function index(Request $request): View
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));
        $orders = MallOrder::query()
            ->orderByDesc('id')
            ->paginate($perPage);

        return view('admin.orders.index', [
            'orders' => $orders,
            'statuses' => MallOrderStatus::cases(),
        ]);
    }

    public function show(int $id): View
    {
        $order = MallOrder::query()->with('items')->find($id);
        if ($order === null) {
            abort(404);
        }

        return view('admin.orders.show', [
            'order' => $order,
            'statuses' => MallOrderStatus::cases(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required',
        ]);

        $order = MallOrder::query()->with('items')->find($id);
        if ($order === null) {
            abort(404);
        }

        try {
            $next = MallOrderStatus::fromClient($validated['status']);
        } catch (ValueError) {
            return back()->withErrors(['status' => 'Invalid status.']);
        }

        try {
            $this->orders->transitionStatus($order, $next);
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        if ($request->input('redirect_to') === 'list') {
            return redirect()->route('admin.orders.index')->with('status', 'Order updated.');
        }

        return redirect()->route('admin.orders.show', $id)
            ->with('status', 'Order updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $order = MallOrder::query()->with('items')->find($id);
        if ($order === null) {
            abort(404);
        }

        DB::transaction(static function () use ($order): void {
            $order->items()->delete();
            $order->delete();
        });

        return redirect()->route('admin.orders.index')->with('status', 'Order deleted.');
    }
}
