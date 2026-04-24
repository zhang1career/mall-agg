<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use App\Enums\TccCancelReason;
use App\Services\api_gw\ResolvedApiGatewayBaseUrl;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class TccCoordinatorClient
{
    public function __construct(
        private ResolvedApiGatewayBaseUrl $gateway,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function begin(array $body): array
    {
        $url = $this->gateway->resolvePathSuffix('/api/tcc/transactions/begin');
        if ($url === '') {
            throw new RuntimeException('API gateway base URL is not configured.');
        }
        $timeout = (int) config('mall_agg.tcc.timeout_seconds', 15);
        $response = Http::timeout($timeout)->acceptJson()->asJson()->post($url, $body);
        if (! $response->successful()) {
            throw new RuntimeException('TCC begin HTTP '.$response->status());
        }

        return CoordinatorEnvelope::dataOrFail($response->json(), 'tcc begin');
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(?int $idemKey = null, ?string $globalTxId = null): array
    {
        if ($idemKey === null && $globalTxId === null) {
            throw new RuntimeException('idem_key or global_tx_id required.');
        }
        $url = $this->gateway->resolvePathSuffix('/api/tcc/transactions/detail');
        if ($url === '') {
            throw new RuntimeException('API gateway base URL is not configured.');
        }
        $timeout = (int) config('mall_agg.tcc.timeout_seconds', 15);
        $query = [];
        if ($idemKey !== null) {
            $query['idem_key'] = $idemKey;
        }
        if ($globalTxId !== null) {
            $query['global_tx_id'] = $globalTxId;
        }
        $response = Http::timeout($timeout)->acceptJson()->get($url, $query);
        if (! $response->successful()) {
            throw new RuntimeException('TCC detail HTTP '.$response->status());
        }

        return CoordinatorEnvelope::dataOrFail($response->json(), 'tcc detail');
    }

    /**
     * @return array<string, mixed>
     */
    public function confirm(string $globalTxId): array
    {
        $url = $this->gateway->resolvePathSuffix('/api/tcc/tx/'.$globalTxId.'/confirm');
        if ($url === '') {
            throw new RuntimeException('API gateway base URL is not configured.');
        }
        $timeout = (int) config('mall_agg.tcc.timeout_seconds', 15);
        $response = Http::timeout($timeout)->acceptJson()->post($url, []);
        if (! $response->successful()) {
            throw new RuntimeException('TCC confirm HTTP '.$response->status());
        }

        return CoordinatorEnvelope::dataOrFail($response->json(), 'tcc confirm');
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(string $globalTxId, TccCancelReason $reason = TccCancelReason::Unpaid): array
    {
        $url = $this->gateway->resolvePathSuffix('/api/tcc/tx/'.$globalTxId.'/cancel');
        if ($url === '') {
            throw new RuntimeException('API gateway base URL is not configured.');
        }
        $timeout = (int) config('mall_agg.tcc.timeout_seconds', 15);
        $response = Http::timeout($timeout)->acceptJson()->asJson()->post($url, [
            'cancel_reason' => $reason->value,
        ]);
        if (! $response->successful()) {
            throw new RuntimeException('TCC cancel HTTP '.$response->status());
        }

        return CoordinatorEnvelope::dataOrFail($response->json(), 'tcc cancel');
    }
}
