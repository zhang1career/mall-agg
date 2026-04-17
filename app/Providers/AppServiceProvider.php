<?php

namespace App\Providers;

use App\Infrastructure\ServiceDiscovery\LaravelRedisStringClient;
use App\Queue\Connectors\DatabaseMillisConnector;
use App\Queue\Failed\DatabaseUuidFailedJobProviderMillis;
use App\Services\Mall\MallCatalogService;
use App\Services\Mall\OrderCommandService;
use App\Services\Mall\ProductInventoryService;
use App\Services\Mall\ProductPriceService;
use App\Services\Mall\ServFd\CmsProductClient;
use App\Services\Mall\ServFd\SearchRecClient;
use App\Services\User\ResolvedFoundationBaseUrl;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Paganini\Capability\ProviderRegistry;
use Paganini\Memo\ApcuMemoStore;
use Paganini\Memo\ArrayMemoStore;
use Paganini\Memo\Memoizer;
use Paganini\ServiceDiscovery\Contracts\ServiceUriResolverInterface;
use Paganini\ServiceDiscovery\RedisServiceUriResolver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LaravelRedisStringClient::class, function (Application $app) {
            $conn = (string) config('mall_agg.foundation.service_discovery.redis_connection', 'default');

            return new LaravelRedisStringClient($app['redis']->connection($conn));
        });

        $this->app->singleton(ServiceUriResolverInterface::class, function (Application $app) {
            return new RedisServiceUriResolver(
                $app->make(LaravelRedisStringClient::class),
                (string) config('mall_agg.foundation.service_discovery.redis_key_prefix', '')
            );
        });

        $this->app->singleton(ResolvedFoundationBaseUrl::class, function (Application $app) {
            $ttl = (int) config('mall_agg.foundation.service_discovery.memo_ttl_seconds', 60);
            if ($ttl < 0) {
                $ttl = 0;
            }
            $store = \function_exists('apcu_fetch') ? new ApcuMemoStore('mall_agg.foundation_base') : new ArrayMemoStore;

            return new ResolvedFoundationBaseUrl(
                $app,
                new Memoizer($store),
                $ttl
            );
        });

        $this->app->singleton(CmsProductClient::class, fn () => CmsProductClient::fromConfig());
        $this->app->singleton(SearchRecClient::class, fn () => SearchRecClient::fromConfig());
        $this->app->singleton(ProductPriceService::class);
        $this->app->singleton(ProductInventoryService::class);
        $this->app->singleton(OrderCommandService::class);
        $this->app->singleton(MallCatalogService::class);

        $this->app->singleton(ProviderRegistry::class, function ($app) {
            $serviceDefs = (array) config('mall_agg.business_services', []);
            $serviceClasses = [];
            foreach ($serviceDefs as $def) {
                if (is_string($def)) {
                    $serviceClasses[] = $def;

                    continue;
                }
                if (is_array($def) && ($def['enabled'] ?? true) === true && is_string($def['class'] ?? null)) {
                    $serviceClasses[] = $def['class'];
                }
            }

            $services = array_map(fn (string $class) => $app->make($class), $serviceClasses);

            return new ProviderRegistry($services);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Use custom database queue with ct and millisecond timestamps
        $this->app['queue']->addConnector('database', function () {
            return new DatabaseMillisConnector($this->app['db']);
        });

        // Use custom failed job provider with failed_at in milliseconds
        $this->app->extend('queue.failer', function ($failer, $app) {
            $config = $app['config']['queue.failed'];
            if (isset($config['driver']) && $config['driver'] === 'database-uuids') {
                return new DatabaseUuidFailedJobProviderMillis(
                    $app['db'],
                    $config['database'] ?? $app['config']['database.default'],
                    $config['table']
                );
            }

            return $failer;
        });
    }
}
