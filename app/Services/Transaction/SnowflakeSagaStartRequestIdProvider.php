<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use App\Services\api_gw\LaravelJsonPostClient;
use App\Services\api_gw\ResolvedApiGatewayBaseUrl;
use Paganini\Foundation\Snowflake\SnowflakeIdHttpClient;
use RuntimeException;

/**
 * {@code POST /api/snowflake/id} via resolved {@see ResolvedApiGatewayBaseUrl} (API_GATEWAY_BASE_URL).
 */
final readonly class SnowflakeSagaStartRequestIdProvider implements SagaStartRequestIdProvider
{
    public function __construct(
        private ResolvedApiGatewayBaseUrl $gateway,
        private string $accessKey,
        private int $timeoutSeconds,
    ) {}

    public function requestIdForSagaStart(): ?string
    {
        $base = $this->gateway->resolve();
        if ($base === '') {
            throw new RuntimeException('API gateway base URL is not configured; cannot request snowflake id.');
        }
        $http = new LaravelJsonPostClient($base, max(1, $this->timeoutSeconds));
        $snow = new SnowflakeIdHttpClient($http, $this->accessKey);

        return $snow->generateIdString();
    }
}
