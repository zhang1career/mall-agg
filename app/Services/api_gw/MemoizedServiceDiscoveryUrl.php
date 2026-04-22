<?php

declare(strict_types=1);

namespace App\Services\api_gw;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use JsonException;
use Paganini\Memo\CacheKeyGenerator;
use Paganini\Memo\Memoizer;
use Paganini\ServiceDiscovery\Contracts\ServiceUriResolverInterface;
use Paganini\ServiceDiscovery\ServiceUrlSpecifier;

/**
 * Resolves URLs that may contain service-discovery host placeholders `://{{service_key}}`
 * via Redis when present; otherwise returns the value rtrimmed of trailing slashes.
 *
 * Memoizes per {@see $cacheKeyPrefix} and raw template to limit Redis reads (TTL from container wiring).
 * When the template does not contain `://{{`, Redis is never touched.
 */
final readonly class MemoizedServiceDiscoveryUrl
{
    public function __construct(
        private Application $app,
        private Memoizer $memoizer,
        private int $memoTtlSeconds,
    ) {}

    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function resolveRtrimmed(string $raw, string $cacheKeyPrefix): string
    {
        if ($raw === '') {
            return '';
        }
        if (! str_contains($raw, '://{{')) {
            return rtrim($raw, '/');
        }

        $cacheKey = $cacheKeyPrefix.':'.CacheKeyGenerator::fromAssociativeArray(['u' => $raw]);

        return rtrim(
            $this->memoizer->getOrCompute(
                $cacheKey,
                $this->memoTtlSeconds,
                fn (): string => ServiceUrlSpecifier::specifyHost(
                    $raw,
                    $this->app->make(ServiceUriResolverInterface::class)
                )
            ),
            '/'
        );
    }
}
