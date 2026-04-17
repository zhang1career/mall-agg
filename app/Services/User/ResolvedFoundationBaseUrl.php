<?php

declare(strict_types=1);

namespace App\Services\User;

use Illuminate\Contracts\Foundation\Application;
use Paganini\Memo\CacheKeyGenerator;
use Paganini\Memo\Memoizer;
use Paganini\ServiceDiscovery\Contracts\ServiceUriResolverInterface;
use Paganini\ServiceDiscovery\ServiceUrlSpecifier;

/**
 * Resolves `mall_agg.foundation.base_url` (from API_GATEWAY_BASE_URL): Fusio-compatible `://{{service_key}}`
 * via Redis when present; otherwise returns trimmed URL unchanged.
 *
 * Memoizes resolved URLs to avoid Redis on every request (TTL from config).
 * {@see ServiceUriResolverInterface} is resolved only when the template contains `://{{` (lazy), so plain URLs never open Redis.
 */
final class ResolvedFoundationBaseUrl
{
    public function __construct(
        private readonly Application $app,
        private readonly Memoizer $memoizer,
        private readonly int $memoTtlSeconds,
    ) {}

    /**
     * Trimmed base URL, or empty string if unset.
     */
    public function resolve(): string
    {
        $raw = (string) config('mall_agg.foundation.base_url', '');
        if ($raw === '') {
            return '';
        }
        if (! str_contains($raw, '://{{')) {
            return rtrim($raw, '/');
        }

        $cacheKey = 'mall_agg:foundation_base:'.CacheKeyGenerator::fromAssociativeArray(['u' => $raw]);

        return rtrim(
            $this->memoizer->getOrCompute(
                $cacheKey,
                $this->memoTtlSeconds,
                fn (): string => ServiceUrlSpecifier::specifyHost(
                    $raw,
                    $this->app->make(ServiceUriResolverInterface::class),
                    null
                )
            ),
            '/'
        );
    }

    /**
     * Same resolution rules as {@see resolve()} for a raw URL from another config (e.g. mall_upload OSS base, serv-fd clients).
     */
    public function resolveRaw(string $raw): string
    {
        if ($raw === '') {
            return '';
        }
        if (! str_contains($raw, '://{{')) {
            return rtrim($raw, '/');
        }

        $cacheKey = 'mall_agg:foundation_base:'.CacheKeyGenerator::fromAssociativeArray(['u' => $raw]);

        return rtrim(
            $this->memoizer->getOrCompute(
                $cacheKey,
                $this->memoTtlSeconds,
                fn (): string => ServiceUrlSpecifier::specifyHost(
                    $raw,
                    $this->app->make(ServiceUriResolverInterface::class),
                    null
                )
            ),
            '/'
        );
    }
}
