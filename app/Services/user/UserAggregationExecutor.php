<?php

namespace App\Services\user;

use Illuminate\Support\Facades\Concurrency;
use Paganini\Aggregation\DTO\AggregationResult;
use Paganini\Aggregation\Execution\AggregationExecutor as BaseAggregationExecutor;
use Paganini\Capability\ProviderContract;

class UserAggregationExecutor
{
    /**
     * @param array<ProviderContract> $providers
     */
    public function execute(array $providers, array $baseUser, array $context, UserDegradePolicy $degradePolicy): AggregationResult
    {
        $mode = (string)config('mall_agg.execution.mode', 'serial');
        $executor = new BaseAggregationExecutor;

        return $executor->execute(
            $providers,
            $baseUser,
            $context,
            $degradePolicy,
            $mode,
            fn(array $tasks): array => Concurrency::run($tasks)
        );
    }
}
