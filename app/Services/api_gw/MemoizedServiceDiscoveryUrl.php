<?php

declare(strict_types=1);

namespace App\Services\api_gw;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use JsonException;
use Paganini\ServiceDiscovery\Contracts\ServiceUriResolverInterface;
use Paganini\ServiceDiscovery\ServiceUrlSpecifier;

/**
 * Resolves URLs that may contain service-discovery host placeholders `://{{service_key}}`
 * via Redis when present; otherwise returns the value rtrimmed of trailing slashes.
 *
 * For service-discovery templates, always resolves via Redis on each call so host
 * selection from comma-separated candidates is performed per request.
 * When the template does not contain `://{{`, Redis is never touched.
 */
final readonly class MemoizedServiceDiscoveryUrl
{
    public function __construct(
        private Application $app,
    ) {}

    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function resolveRtrimmed(string $raw, string $_cacheKeyPrefix): string
    {
        if ($raw === '') {
            return '';
        }
        if (! str_contains($raw, '://{{')) {
            return rtrim($raw, '/');
        }

        return rtrim(
            ServiceUrlSpecifier::specifyHost(
                $raw,
                $this->app->make(ServiceUriResolverInterface::class)
            ),
            '/'
        );
    }
}
