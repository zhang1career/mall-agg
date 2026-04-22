<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PointsHoldState;
use App\Models\Concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Points ledger rows: TCC hold lifecycle (try / confirm / cancel) and manual admin entries (`AdminLedger`).
 *
 * @property int $id
 * @property int $uid
 * @property int $oid 0 = not linked to an order
 * @property int $amount_minor Points amount in minor units (same scale as balance_minor); 0 = none
 * @property PointsHoldState $state
 * @property string|null $tcc_idem_key TCC idempotency key; null for admin ledger rows or unassigned (unique when set)
 * @property int $ct
 * @property int $ut
 */
class PointsFlow extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'points_flow';

    protected $fillable = [
        'uid',
        'oid',
        'amount_minor',
        'state',
        'tcc_idem_key',
        'ct',
        'ut',
    ];

    protected $casts = [
        'uid' => 'integer',
        'oid' => 'integer',
        'amount_minor' => 'integer',
        'state' => PointsHoldState::class,
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * @return BelongsTo<MallOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(MallOrder::class, 'oid');
    }
}
