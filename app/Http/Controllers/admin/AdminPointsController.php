<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

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
        $tab = $request->query('tab') === 'flows' ? 'flows' : 'balances';
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        if ($tab === 'balances') {
            $balances = MallPointsBalance::query()->orderByDesc('id')->paginate($perPage)->withQueryString();

            return view('admin.points.index', [
                'tab' => $tab,
                'balances' => $balances,
                'flows' => null,
            ]);
        }

        $flows = PointsFlow::query()->orderByDesc('id')->paginate($perPage)->withQueryString();

        return view('admin.points.index', [
            'tab' => $tab,
            'balances' => null,
            'flows' => $flows,
        ]);
    }

    public function showBalance(int $id): View
    {
        $row = MallPointsBalance::query()->findOrFail($id);

        return view('admin.points.balances.show', ['balance' => $row]);
    }

    public function showFlow(int $id): View
    {
        $row = PointsFlow::query()->findOrFail($id);

        return view('admin.points.flows.show', ['flow' => $row]);
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
            return redirect()->route('admin.points.index', ['tab' => 'balances'])
                ->withErrors(['account' => $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('admin.points.index', ['tab' => 'balances'])->with('status', 'Points account created.');
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
            return redirect()->route('admin.points.index', ['tab' => 'balances'])
                ->withErrors(['adjust' => $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('admin.points.index', ['tab' => 'balances'])->with('status', 'Points balance updated.');
    }

    public function destroyBalance(int $id): RedirectResponse
    {
        try {
            $this->adminPoints->deleteBalanceById($id);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.points.index', ['tab' => 'balances'])
                ->withErrors(['delete' => $e->getMessage()]);
        }

        return redirect()->route('admin.points.index', ['tab' => 'balances'])->with('status', 'Account deleted.');
    }

    public function destroyFlow(int $id): RedirectResponse
    {
        try {
            $this->adminPoints->deleteFlowById($id);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.points.index', ['tab' => 'flows'])
                ->withErrors(['delete' => $e->getMessage()]);
        }

        return redirect()->route('admin.points.index', ['tab' => 'flows'])->with('status', 'Flow row deleted.');
    }
}
