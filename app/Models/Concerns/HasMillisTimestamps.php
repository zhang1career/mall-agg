<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

trait HasMillisTimestamps
{
    public static function bootHasMillisTimestamps(): void
    {
        static::creating(function (Model $model): void {
            $now = self::nowMillis();
            if ((int) ($model->getAttribute('ct') ?? 0) === 0) {
                $model->setAttribute('ct', $now);
            }
            $model->setAttribute('ut', $now);
        });

        static::updating(function (Model $model): void {
            $model->setAttribute('ut', self::nowMillis());
        });
    }

    public static function nowMillis(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
