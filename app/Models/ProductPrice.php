<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $pid CMS product id
 * @property int $price Price in minor units (e.g. cents)
 * @property int $ct
 * @property int $ut
 */
class ProductPrice extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'product_price';

    protected $fillable = [
        'pid',
        'price',
        'ct',
        'ut',
    ];

    protected $casts = [
        'pid' => 'integer',
        'price' => 'integer',
        'ct' => 'integer',
        'ut' => 'integer',
    ];
}
