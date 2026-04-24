<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use App\Services\api_gw\ResolvedApiGatewayBaseUrl;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class SagaCoordinatorClient
{
    public function __construct(
        private ResolvedApiGatewayBaseUrl $gateway,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function start(array $body): array
    {
        $url = $this->gateway->resolvePathSuffix('/api/saga/instances');
        if ($url === '') {
            throw new RuntimeException('API gateway base URL is not configured.');
        }
        $timeout = (int) config('mall_agg.saga.timeout_seconds', 10);
        $response = Http::timeout($timeout)->acceptJson()->asJson()->post($url, $body);
        if (! $response->successful()) {
            throw new RuntimeException('Saga start HTTP '.$response->status());
        }

        return CoordinatorEnvelope::dataOrFail($response->json(), 'saga start');
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(?int $idemKey = null, ?string $sagaInstanceId = null): array
    {
        if ($idemKey === null && $sagaInstanceId === null) {
            throw new RuntimeException('idem_key or saga_instance_id required.');
        }
        $url = $this->gateway->resolvePathSuffix('/api/saga/instances/detail');
        if ($url === '') {
            throw new RuntimeException('API gateway base URL is not configured.');
        }
        $timeout = (int) config('mall_agg.saga.timeout_seconds', 10);
        $query = [];
        if ($idemKey !== null) {
            $query['idem_key'] = $idemKey;
        }
        if ($sagaInstanceId !== null) {
            $query['saga_instance_id'] = $sagaInstanceId;
        }
        $response = Http::timeout($timeout)->acceptJson()->get($url, $query);
        if (! $response->successful()) {
            throw new RuntimeException('Saga detail HTTP '.$response->status());
        }

        return CoordinatorEnvelope::dataOrFail($response->json(), 'saga detail');
    }
}
