<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $oid References order.id
 * @property int $pid CMS product id
 * @property int $quantity
 * @property int $unit_price Snapshot unit price (minor units)
 * @property int $ct
 * @property int $ut
 */
class MallOrderItem extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'order_item';

    protected $fillable = [
        'oid',
        'pid',
        'quantity',
        'unit_price',
        'ct',
        'ut',
    ];

    protected $casts = [
        'oid' => 'integer',
        'pid' => 'integer',
        'quantity' => 'integer',
        'unit_price' => 'integer',
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
