<?php

namespace App\Services\User;

use Paganini\Aggregation\Policies\DefaultDegradePolicy;

class UserDegradePolicy extends DefaultDegradePolicy
{
    public function __construct()
    {
        parent::__construct(
            (string) config('mall_agg.degrade.strategy', self::STRATEGY_MASK_NULL),
            (string) config('mall_agg.degrade.mask_error_message', 'Service temporarily unavailable.')
        );
    }
}
