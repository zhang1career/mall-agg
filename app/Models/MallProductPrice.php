<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $product_id
 * @property int $price_minor
 * @property int $ct
 * @property int $ut
 */
class MallProductPrice extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'mall_product_price';

    protected $fillable = [
        'product_id',
        'price_minor',
        'ct',
        'ut',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'price_minor' => 'integer',
        'ct' => 'integer',
        'ut' => 'integer',
    ];
}
