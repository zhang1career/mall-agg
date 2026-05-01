<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MallPointsBalance;
use App\Models\PointsFlow;
use App\Services\mall\MallPointsAdminService;
use App\Support\MillisTimestampDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MallAdminPointsController extends Controller
{
    public function __construct(
        private readonly MallPointsAdminService $adminPoints,
    ) {}

    public function storeAccount(Request $request): JsonResponse
    {
        $request->validate([
            'uid' => 'required|integer|min:1',
            'balance_minor' => 'sometimes|integer|min:0',
        ]);

        $balance = $this->adminPoints->openAccount(
            (int) $request->input('uid'),
            (int) $request->input('balance_minor', 0),
        );

        $this->logHandledApiRequest($request, ['handler' => 'mall.admin.points.accounts.store', 'uid' => $balance->uid]);

        return response()->json(ApiResponse::ok(['account' => $this->accountRow($balance)]), 201);
    }

    public function adjust(Request $request): JsonResponse
    {
        $request->validate([
            'uid' => 'required|integer|min:1',
            'delta_minor' => 'required|integer|not_in:0',
            'oid' => 'sometimes|integer|min:0',
        ]);

        $out = $this->adminPoints->adjustBalance(
            (int) $request->input('uid'),
            (int) $request->input('delta_minor'),
            (int) $request->input('oid', 0),
        );

        $this->logHandledApiRequest($request, [
            'handler' => 'mall.admin.points.adjust',
            'uid' => $out['balance']->uid,
            'flow_id' => $out['flow']->id,
        ]);

        return response()->json(ApiResponse::ok([
            'account' => $this->accountRow($out['balance']),
            'flow' => $this->flowRow($out['flow']),
        ]));
    }

    /** @return array<string, int|string|null> */
    private function accountRow(MallPointsBalance $b): array
    {
        return [
            'id' => $b->id,
            'uid' => $b->uid,
            'balance_minor' => $b->balance_minor,
            'ct' => MillisTimestampDisplay::format($b->ct),
            'ut' => MillisTimestampDisplay::format($b->ut),
        ];
    }

    /** @return array<string, int|string|null> */
    private function flowRow(PointsFlow $f): array
    {
        return [
            'id' => $f->id,
            'uid' => $f->uid,
            'oid' => $f->oid,
            'amount_minor' => $f->amount_minor,
            'state' => $f->state->value,
            'tcc_idem_key' => $f->tcc_idem_key,
            'ct' => MillisTimestampDisplay::format($f->ct),
            'ut' => MillisTimestampDisplay::format($f->ut),
        ];
    }
}
