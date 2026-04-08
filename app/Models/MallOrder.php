<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MallOrderStatus;
use App\Models\Concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property MallOrderStatus $status
 * @property int $total_amount_minor
 * @property int $ct
 * @property int $ut
 * @property-read Collection<int, MallOrderItem> $items
 */
class MallOrder extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'mall_order';

    protected $fillable = [
        'user_id',
        'status',
        'total_amount_minor',
        'ct',
        'ut',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'status' => MallOrderStatus::class,
        'total_amount_minor' => 'integer',
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * @return HasMany<MallOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MallOrderItem::class, 'order_id');
    }
}
