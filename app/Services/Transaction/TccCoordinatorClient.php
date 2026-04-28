<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use App\Enums\TccCancelReason;
use App\Services\api_gw\ResolvedApiGatewayBaseUrl;
use App\Services\mall\serv_fd\TccTxClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class TccCoordinatorClient
{
    public function __construct(
        private ResolvedApiGatewayBaseUrl $gateway,
        private TccTxClient               $foundationTccTx,
    ) {}

    /**
     * @param  array<string, mixed>  $body  Must include `branches` (see OpenAPI TccBeginRequest). `biz_id` is set from config.
     * @return array<string, mixed>
     */
    public function begin(array $body): array
    {
        $url = $this->gateway->resolvePathSuffix('/api/tcc/tx');
        if ($url === '') {
            throw new RuntimeException('API gateway base URL is not configured.');
        }
        $bizId = (int) config('mall_agg.tcc.flow_id', 0);
        if ($bizId < 1) {
            throw new RuntimeException('mall_agg.tcc.flow_id (MALL_TCC_FLOW_ID) is not configured.');
        }
        $body['biz_id'] = $bizId;
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
    public function detail(int|string $idemKey): array
    {
        $key = trim((string) $idemKey);
        if ($key === '' || ! ctype_digit($key)) {
            throw new RuntimeException('idem_key must be a non-empty decimal string.');
        }
        $url = $this->gateway->resolvePathSuffix('/api/tcc/tx/'.rawurlencode($key));
        if ($url === '') {
            throw new RuntimeException('API gateway base URL is not configured.');
        }
        $timeout = (int) config('mall_agg.tcc.timeout_seconds', 15);
        $response = Http::timeout($timeout)->acceptJson()->get($url);
        if (! $response->successful()) {
            throw new RuntimeException('TCC detail HTTP '.$response->status());
        }

        return CoordinatorEnvelope::dataOrFail($response->json(), 'tcc detail');
    }

    /**
     * @return array<string, mixed>
     */
    public function confirm(int|string $idemKey): array
    {
        return $this->foundationTccTx->confirm((string) $idemKey);
    }

    /**
     * Calls app_tcc `POST /api/tcc/tx/{idem_key}/cancel`. `cancel_reason` is optional on the coordinator;
     * this client still sends {@see TccCancelReason} for clarity — empty `{}` is also accepted server-side.
     *
     * @return array<string, mixed>
     */
    public function cancel(int|string $idemKey, TccCancelReason $reason = TccCancelReason::Unpaid): array
    {
        $key = trim((string) $idemKey);
        if ($key === '' || ! ctype_digit($key)) {
            throw new RuntimeException('idem_key must be a non-empty decimal string.');
        }
        $url = $this->gateway->resolvePathSuffix('/api/tcc/tx/'.rawurlencode($key).'/cancel');
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
