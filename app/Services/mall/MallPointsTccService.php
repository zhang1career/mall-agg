<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\PointsHoldState;
use App\Models\MallPointsBalance;
use App\Models\PointsFlow;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MallPointsTccService
{
    /**
     * Available points for the user (minor units); no account row yields 0.
     */
    public function availableBalanceMinor(int $uid): int
    {
        $row = MallPointsBalance::query()->where('uid', $uid)->first();
        if ($row === null) {
            return 0;
        }

        return (int) $row->balance_minor;
    }

    public function ensureAccount(int $uid): MallPointsBalance
    {
        $row = MallPointsBalance::query()->where('uid', $uid)->first();
        if ($row !== null) {
            return $row;
        }

        $row = new MallPointsBalance(['uid' => $uid, 'balance_minor' => 0]);
        $row->save();

        return $row;
    }

    /**
     * TCC Try: move amount from available balance into hold.
     */
    public function tryFreeze(int $uid, int $amountMinor, ?int $orderId, string $tccIdemKey): void
    {
        if ($amountMinor < 1) {
            throw new RuntimeException('Points amount must be positive.');
        }

        DB::transaction(function () use ($uid, $amountMinor, $orderId, $tccIdemKey): void {
            $existing = PointsFlow::query()->where('tcc_idem_key', $tccIdemKey)->first();
            if ($existing !== null) {
                if ($existing->state === PointsHoldState::TrySucceeded) {
                    return;
                }
                throw new RuntimeException('Duplicate tcc_idem_key with invalid state.');
            }

            $balance = MallPointsBalance::query()->where('uid', $uid)->lockForUpdate()->first();
            if ($balance === null) {
                MallPointsBalance::query()->create([
                    'uid' => $uid,
                    'balance_minor' => 0,
                ]);
                $balance = MallPointsBalance::query()->where('uid', $uid)->lockForUpdate()->first();
            }
            if ($balance === null) {
                throw new RuntimeException('Points balance missing.');
            }
            if ($balance->balance_minor < $amountMinor) {
                throw new RuntimeException('Insufficient points.');
            }

            $balance->balance_minor -= $amountMinor;
            $balance->save();

            $hold = new PointsFlow([
                'uid' => $uid,
                'oid' => $orderId ?? 0,
                'amount_minor' => $amountMinor,
                'state' => PointsHoldState::TrySucceeded,
                'tcc_idem_key' => $tccIdemKey,
            ]);
            $hold->save();
        });
    }

    public function confirm(string $tccIdemKey): void
    {
        DB::transaction(function () use ($tccIdemKey): void {
            $hold = PointsFlow::query()->where('tcc_idem_key', $tccIdemKey)->lockForUpdate()->first();
            if ($hold === null) {
                return;
            }
            if ($hold->state === PointsHoldState::Confirmed) {
                return;
            }
            if ($hold->state !== PointsHoldState::TrySucceeded) {
                throw new RuntimeException('Points hold not in try-succeeded state.');
            }
            $hold->state = PointsHoldState::Confirmed;
            $hold->save();
        });
    }

    public function cancel(string $tccIdemKey): void
    {
        DB::transaction(function () use ($tccIdemKey): void {
            $hold = PointsFlow::query()->where('tcc_idem_key', $tccIdemKey)->lockForUpdate()->first();
            if ($hold === null) {
                return;
            }
            if ($hold->state === PointsHoldState::RolledBack) {
                return;
            }
            if ($hold->state === PointsHoldState::Confirmed) {
                throw new RuntimeException('Cannot cancel confirmed hold.');
            }
            if ($hold->state !== PointsHoldState::TrySucceeded) {
                throw new RuntimeException('Points hold not in try-succeeded state.');
            }

            $balance = MallPointsBalance::query()->where('uid', $hold->uid)->lockForUpdate()->first();
            if ($balance === null) {
                throw new RuntimeException('Points balance missing.');
            }
            $balance->balance_minor += $hold->amount_minor;
            $balance->save();

            $hold->state = PointsHoldState::RolledBack;
            $hold->save();
        });
    }
}
