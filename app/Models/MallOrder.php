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
 * @property int $uid Foundation user id (from GET /api/user/me)
 * @property MallOrderStatus $status Stored as integer (see MallOrderStatus backed values)
 * @property int $total_price Order total in minor units (e.g. cents); denormalized at creation from line items
 * @property int $ct
 * @property int $ut
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
        'ct',
        'ut',
    ];

    protected $casts = [
        'uid' => 'integer',
        'status' => MallOrderStatus::class,
        'total_price' => 'integer',
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * @return HasMany<MallOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MallOrderItem::class, 'oid');
    }
}
