<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $uid
 * @property int $balance_minor
 * @property int $ct
 * @property int $ut
 */
class MallPointsBalance extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'points_balance';

    protected $fillable = [
        'uid',
        'balance_minor',
        'ct',
        'ut',
    ];

    protected $casts = [
        'id' => 'integer',
        'uid' => 'integer',
        'balance_minor' => 'integer',
        'ct' => 'integer',
        'ut' => 'integer',
    ];
}
