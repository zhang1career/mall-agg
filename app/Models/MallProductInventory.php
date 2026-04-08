<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $product_id
 * @property int $quantity
 * @property int $ct
 * @property int $ut
 */
class MallProductInventory extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'mall_product_inventory';

    protected $fillable = [
        'product_id',
        'quantity',
        'ct',
        'ut',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'quantity' => 'integer',
        'ct' => 'integer',
        'ut' => 'integer',
    ];
}
