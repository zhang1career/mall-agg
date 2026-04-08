<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $pid CMS product id
 * @property int $quantity
 * @property int $ct
 * @property int $ut
 */
class ProductInventory extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'product_inventory';

    protected $fillable = [
        'pid',
        'quantity',
        'ct',
        'ut',
    ];

    protected $casts = [
        'pid' => 'integer',
        'quantity' => 'integer',
        'ct' => 'integer',
        'ut' => 'integer',
    ];
}
