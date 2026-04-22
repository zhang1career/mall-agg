<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MallPointsBalance;
use App\Models\PointsFlow;
use App\Services\mall\MallPointsAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AdminPointsController extends Controller
{
    public function __construct(
        private readonly MallPointsAdminService $adminPoints,
    ) {}

    public function index(Request $request): View
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        return view('admin.points.index', [
            'balances' => MallPointsBalance::query()->orderByDesc('id')->paginate($perPage),
            'recentFlows' => PointsFlow::query()->orderByDesc('id')->limit(25)->get(),
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'uid' => 'required|integer|min:1',
            'balance_minor' => 'nullable|integer|min:0',
        ]);

        try {
            $this->adminPoints->openAccount((int) $d['uid'], (int) ($d['balance_minor'] ?? 0));
        } catch (RuntimeException $e) {
            return back()->withErrors(['account' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.points.index')->with('status', 'Points account created.');
    }

    public function adjust(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'uid' => 'required|integer|min:1',
            'delta_minor' => 'required|integer|not_in:0',
            'oid' => 'nullable|integer|min:0',
        ]);

        try {
            $this->adminPoints->adjustBalance(
                (int) $d['uid'],
                (int) $d['delta_minor'],
                (int) ($d['oid'] ?? 0),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['adjust' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.points.index')->with('status', 'Points balance updated.');
    }
}
