<?php

namespace App\Providers;

use App\Contracts\InventoryOutboundContract;
use App\Contracts\PaymentOutboundContract;
use App\Http\Client\OutboundHttpDebugMiddleware;
use App\Infrastructure\ServiceDiscovery\LaravelRedisStringClient;
use App\Logging\monolog\TodayAppLogHandler;
use App\Queue\Connectors\DatabaseMillisConnector;
use App\Queue\Failed\DatabaseUuidFailedJobProviderMillis;
use App\Services\api_gw\MemoizedServiceDiscoveryUrl;
use App\Services\api_gw\ResolvedApiGatewayBaseUrl;
use App\Services\api_gw\ResolvedXxlJobAdminAddress;
use App\Services\mall\CheckoutOrchestrator;
use App\Services\mall\Internal\InternalInventoryParticipantService;
use App\Services\mall\Internal\InternalOrderParticipantService;
use App\Services\mall\Internal\InternalPayParticipantService;
use App\Services\mall\MallCatalogService;
use App\Services\mall\MallOverdueOrderSweepService;
use App\Services\mall\MallPaymentCallbackService;
use App\Services\mall\MallPointsTccService;
use App\Services\mall\OrderCommandService;
use App\Services\mall\ProductInventoryService;
use App\Services\mall\ProductPriceService;
use App\Services\mall\serv_fd\CmsProductClient;
use App\Services\mall\serv_fd\SearchRecClient;
use App\Services\Outbound\StubInventoryOutboundClient;
use App\Services\Outbound\StubPaymentOutboundClient;
use App\Services\Transaction\SagaCoordinatorClient;
use App\Services\Transaction\TccCoordinatorClient;
use App\Services\XxlJobRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Paganini\Capability\ProviderRegistry;
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
            $conn = (string) config('mall_agg.foundation.service_discovery.redis_connection');

            return new LaravelRedisStringClient($app['redis']->connection($conn));
        });

        $this->app->singleton(ServiceUriResolverInterface::class, function (Application $app) {
            return new RedisServiceUriResolver(
                $app->make(LaravelRedisStringClient::class),
                (string) config('mall_agg.foundation.service_discovery.redis_key_prefix')
            );
        });

        $this->app->singleton(MemoizedServiceDiscoveryUrl::class, function (Application $app) {
            return new MemoizedServiceDiscoveryUrl(
                $app,
            );
        });

        $this->app->singleton(ResolvedApiGatewayBaseUrl::class, function (Application $app) {
            return new ResolvedApiGatewayBaseUrl(
                $app->make(MemoizedServiceDiscoveryUrl::class)
            );
        });

        $this->app->singleton(ResolvedXxlJobAdminAddress::class, function (Application $app) {
            return new ResolvedXxlJobAdminAddress(
                $app->make(MemoizedServiceDiscoveryUrl::class)
            );
        });

        $this->app->singleton(CmsProductClient::class, fn () => CmsProductClient::fromConfig());
        $this->app->singleton(SearchRecClient::class, fn () => SearchRecClient::fromConfig());
        $this->app->singleton(ProductPriceService::class);
        $this->app->singleton(ProductInventoryService::class);
        $this->app->singleton(OrderCommandService::class);
        $this->app->singleton(InternalInventoryParticipantService::class);
        $this->app->singleton(InternalOrderParticipantService::class);
        $this->app->singleton(InternalPayParticipantService::class);
        $this->app->singleton(MallCatalogService::class);
        $this->app->singleton(InventoryOutboundContract::class, StubInventoryOutboundClient::class);
        $this->app->singleton(PaymentOutboundContract::class, StubPaymentOutboundClient::class);
        $this->app->singleton(MallPointsTccService::class);
        $this->app->singleton(SagaCoordinatorClient::class);
        $this->app->singleton(TccCoordinatorClient::class);
        $this->app->singleton(CheckoutOrchestrator::class);
        $this->app->singleton(MallPaymentCallbackService::class);
        $this->app->singleton(MallOverdueOrderSweepService::class);

        $this->app->singleton(XxlJobRegistry::class, function () {
            $registry = new XxlJobRegistry;
            $registry->scanAndRegister('Jobs');

            return $registry;
        });

        $this->app->singleton(ProviderRegistry::class, function ($app) {
            $serviceDefs = (array) config('mall_agg.business_services');
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
        Log::extend('app_today', function ($app, array $config) {
            $handler = new TodayAppLogHandler(
                $config['path'],
                (int) ($config['days'] ?? 0),
                $this->level($config),
                $config['bubble'] ?? true,
                $config['permission'] ?? null,
                $config['locking'] ?? false
            );

            return new Logger(
                $this->parseChannel($config),
                [$this->prepareHandler($handler, $config)],
                $config['replace_placeholders'] ?? false ? [new PsrLogMessageProcessor] : []
            );
        });

        if (config('app.debug')) {
            Http::globalRequestMiddleware([OutboundHttpDebugMiddleware::class, 'logRequest']);
            Http::globalResponseMiddleware([OutboundHttpDebugMiddleware::class, 'logResponse']);
        }

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
