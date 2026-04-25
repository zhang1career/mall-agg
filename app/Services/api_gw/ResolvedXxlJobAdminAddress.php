<?php

declare(strict_types=1);

namespace App\Services\api_gw;

/**
 * Resolves `xxl.admin_address` (from env `XXL_JOB_ADMIN_ADDRESS`) with the same
 * `://{{service_key}}` Redis service-discovery rules as {@see ResolvedApiGatewayBaseUrl}.
 */
final readonly class ResolvedXxlJobAdminAddress
{
    public function __construct(
        private MemoizedServiceDiscoveryUrl $serviceDiscoveryUrl,
    ) {}

    public function resolve(): string
    {
        return $this->serviceDiscoveryUrl->resolveRtrimmed(
            (string) config('xxl.admin_address'),
            'mall_agg:xxl_job_admin'
        );
    }
}
