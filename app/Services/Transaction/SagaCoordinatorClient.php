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
     * GET /api/saga/instances/{instance_id} — path param is the saga instance primary key (decimal string).
     *
     * @return array<string, mixed>
     */
    public function getInstance(string $sagaInstanceId): array
    {
        $id = trim($sagaInstanceId);
        if ($id === '' || ! ctype_digit($id)) {
            throw new RuntimeException('saga_instance_id must be a non-empty decimal string.');
        }
        $url = $this->gateway->resolvePathSuffix('/api/saga/instances/'.rawurlencode($id));
        if ($url === '') {
            throw new RuntimeException('API gateway base URL is not configured.');
        }
        $timeout = (int) config('mall_agg.saga.timeout_seconds', 10);
        $response = Http::timeout($timeout)->acceptJson()->get($url);
        if (! $response->successful()) {
            throw new RuntimeException('Saga instance HTTP '.$response->status());
        }

        return CoordinatorEnvelope::dataOrFail($response->json(), 'saga instance');
    }
}
