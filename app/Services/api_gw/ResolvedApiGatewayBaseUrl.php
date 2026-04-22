<?php

declare(strict_types=1);

namespace App\Services\api_gw;

use Illuminate\Contracts\Container\BindingResolutionException;
use JsonException;

/**
 * Resolves `api_gw.base_url` (from API_GATEWAY_BASE_URL): service-discovery-replaceable `://{{service_key}}`
 * via Redis when present; otherwise returns trimmed URL unchanged.
 *
 * Memoizes resolved URLs to avoid Redis on every request (TTL from config).
 * {@see MemoizedServiceDiscoveryUrl}
 */
final readonly class ResolvedApiGatewayBaseUrl
{
    public function __construct(
        private MemoizedServiceDiscoveryUrl $serviceDiscoveryUrl,
    ) {}

    /**
     * Trimmed gateway base URL, or empty string if unset.
     *
     * @throws JsonException|BindingResolutionException
     */
    public function resolve(): string
    {
        return $this->serviceDiscoveryUrl->resolveRtrimmed(
            (string) config('api_gw.base_url'),
            'mall_agg:api_gw'
        );
    }

    /**
     * Resolved gateway base with a path suffix (e.g. `/api/searchrec`, `/api/oss`). Empty if gateway base is unset.
     */
    public function resolvePathSuffix(string $path): string
    {
        $base = $this->resolve();
        if ($base === '') {
            return '';
        }
        $path = '/'.ltrim($path, '/');

        return $base.$path;
    }
}
