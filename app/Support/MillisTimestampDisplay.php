<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;

final class MillisTimestampDisplay
{
    /**
     * Format a millisecond Unix timestamp for admin UI (app timezone).
     */
    public static function format(?int $millis): string
    {
        if ($millis === null || $millis <= 0) {
            return '—';
        }

        return Carbon::createFromTimestampMs($millis)
            ->timezone((string) config('app.timezone'))
            ->format('Y-m-d H:i:s');
    }
}
