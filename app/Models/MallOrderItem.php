<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property int $quantity
 * @property int $unit_price_minor
 * @property int $ct
 * @property int $ut
 */
class MallOrderItem extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'mall_order_item';

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price_minor',
        'ct',
        'ut',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'integer',
        'unit_price_minor' => 'integer',
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * @return BelongsTo<MallOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(MallOrder::class, 'order_id');
    }
}
