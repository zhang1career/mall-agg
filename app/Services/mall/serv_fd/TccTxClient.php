<?php

declare(strict_types=1);

namespace App\Services\mall\serv_fd;

use App\Services\api_gw\ResolvedApiGatewayBaseUrl;
use App\Services\Transaction\CoordinatorEnvelope;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * service_foundation `app_tcc` HTTP (see `docs/api_tcc.json`): {@code /api/tcc/tx/...}.
 *
 * Base URL and discovery follow {@see ResolvedApiGatewayBaseUrl} / {@code API_GATEWAY_BASE_URL};
 * timeout follows {@code mall_agg.tcc.timeout_seconds} / {@code MALL_TCC_TIMEOUT_SECONDS}.
 */
final readonly class TccTxClient
{
    public function __construct(
        private ResolvedApiGatewayBaseUrl $gateway,
        private int $timeoutSeconds,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            app(ResolvedApiGatewayBaseUrl::class),
            (int) config('mall_agg.tcc.timeout_seconds', 15),
        );
    }

    /**
     * POST {@code /api/tcc/tx/{idem_key}/confirm} (empty JSON body per OpenAPI).
     *
     * @return array<string, mixed>
     * @throws ConnectionException
     */
    public function confirm(string $idemKey): array
    {
        $key = trim($idemKey);
        if ($key === '' || ! ctype_digit($key)) {
            throw new RuntimeException('idem_key must be a non-empty decimal string.');
        }
        $url = $this->gateway->resolvePathSuffix('/api/tcc/tx/'.rawurlencode($key).'/confirm');
        if ($url === '') {
            throw new RuntimeException('API gateway base URL is not configured.');
        }
        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['X-Request-Id' => $key])
            ->post($url);
        if (! $response->successful()) {
            throw new RuntimeException('TCC confirm HTTP '.$response->status());
        }

        return CoordinatorEnvelope::dataOrFail($response->json(), 'tcc confirm');
    }
}
