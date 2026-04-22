<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MallPointsBalance;
use App\Models\PointsFlow;
use App\Services\mall\MallPointsAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

final class MallAdminPointsController extends Controller
{
    public function __construct(
        private readonly MallPointsAdminService $adminPoints,
    ) {}

    public function storeAccount(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'uid' => 'required|integer|min:1',
            'balance_minor' => 'sometimes|integer|min:0',
        ]);
        if ($v->fails()) {
            return response()->json(ApiResponse::error(100, $v->errors()->first()), 422);
        }

        try {
            $balance = $this->adminPoints->openAccount(
                (int) $request->input('uid'),
                (int) $request->input('balance_minor', 0),
            );
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error(40001, $e->getMessage()), 422);
        }

        $this->logHandledApiRequest($request, ['handler' => 'mall.admin.points.accounts.store', 'uid' => $balance->uid]);

        return response()->json(ApiResponse::ok(['account' => $this->accountRow($balance)]), 201);
    }

    public function adjust(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'uid' => 'required|integer|min:1',
            'delta_minor' => 'required|integer|not_in:0',
            'oid' => 'sometimes|integer|min:0',
        ]);
        if ($v->fails()) {
            return response()->json(ApiResponse::error(100, $v->errors()->first()), 422);
        }

        try {
            $out = $this->adminPoints->adjustBalance(
                (int) $request->input('uid'),
                (int) $request->input('delta_minor'),
                (int) $request->input('oid', 0),
            );
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error(40001, $e->getMessage()), 422);
        }

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
            'ct' => $b->ct,
            'ut' => $b->ut,
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
            'ct' => $f->ct,
            'ut' => $f->ut,
        ];
    }
}
