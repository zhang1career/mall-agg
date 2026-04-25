<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\mall\MallOverdueOrderSweepService;
use Illuminate\Support\Facades\Log;
use Paganini\XxlJobExecutor\Attributes\XxlJob;
use Throwable;

/**
 * XXL-Job handlers for mall order lifecycle maintenance.
 */
final class MallOrderMaintenance
{
    /**
     * @return array{0: bool, 1: array<string, int>|null, 2: string|null}
     */
    #[XxlJob('closeExpiredOrders')]
    public static function closeExpiredOrders(mixed $_executorParams): array
    {
        try {
            $stats = app(MallOverdueOrderSweepService::class)->sweepExpired();
            Log::debug('[xxljob] closeExpiredOrders', $stats);

            return [true, $stats, null];
        } catch (Throwable $e) {
            Log::error('[xxljob] closeExpiredOrders failed: '.$e->getMessage());

            return [false, null, $e->getMessage()];
        }
    }
}
