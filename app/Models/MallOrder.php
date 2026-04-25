<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CheckoutPhase;
use App\Enums\MallOrderStatus;
use App\Models\Concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $uid Foundation user id (from GET /api/user/me)
 * @property MallOrderStatus $status Stored as integer (see MallOrderStatus backed values)
 * @property int $total_price Order total in minor units (e.g. cents); denormalized at creation from line items
 * @property int $points_deduct_minor Points applied at checkout (minor units); 0 until checkout
 * @property int $cash_payable_minor Third-party cash amount after points (total_price − points_deduct_minor); 0 until checkout
 * @property int $ct
 * @property int $ut
 * @property int|null $saga_idem_key null = not assigned (unique when set)
 * @property int|null $tcc_idem_key null = not assigned (unique when set)
 * @property string $tid Coordinator global transaction id (TCC); empty string = unset
 * @property CheckoutPhase $checkout_phase 0 = {@see CheckoutPhase::None}
 * @property bool $ext_inventory
 * @property string $ext_id External inventory id; empty string = unset
 * @property-read Collection<int, MallOrderItem> $items
 */
class MallOrder extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    /** @var string SQL reserved word; Laravel quotes identifiers */
    protected $table = 'order';

    protected $fillable = [
        'uid',
        'status',
        'total_price',
        'points_deduct_minor',
        'cash_payable_minor',
        'ct',
        'ut',
        'saga_idem_key',
        'tcc_idem_key',
        'tid',
        'checkout_phase',
        'ext_inventory',
        'ext_id',
    ];

    protected $casts = [
        'uid' => 'integer',
        'status' => MallOrderStatus::class,
        'total_price' => 'integer',
        'points_deduct_minor' => 'integer',
        'cash_payable_minor' => 'integer',
        'ct' => 'integer',
        'ut' => 'integer',
        'saga_idem_key' => 'integer',
        'tcc_idem_key' => 'integer',
        'tid' => 'string',
        'checkout_phase' => CheckoutPhase::class,
        'ext_inventory' => 'boolean',
        'ext_id' => 'string',
    ];

    /**
     * @return HasMany<MallOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MallOrderItem::class, 'oid');
    }
}
