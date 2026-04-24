<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ProductPrice;
use App\Services\mall\CheckoutOrchestrator;
use App\Services\mall\OrderCommandService;
use App\Services\Transaction\SagaCoordinatorClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * @group checkout-orchestrator
 */
final class CheckoutOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_checkout_calls_saga_start_and_merges_coordinator_fields(): void
    {
        config()->set('api_gw.base_url', 'http://foundation.local');
        config()->set('mall_agg.saga.flow_id', 7);
        config()->set('mall_agg.saga.access_key', 'ak');
        config()->set('mall_agg.tcc.flow_id', 501);
        config()->set('mall_agg.tcc.access_key', 'tcc');

        ProductPrice::query()->create([
            'pid' => 88,
            'price' => 1000,
            'ct' => 1,
            'ut' => 1,
        ]);

        $order = app(OrderCommandService::class)->createDraftPendingOrder(33, [['product_id' => 88, 'quantity' => 1]]);

        $saga = Mockery::mock(SagaCoordinatorClient::class);
        $saga->shouldReceive('start')->once()->withArgs(function (array $body): bool {
            return (int) ($body['flow_id'] ?? 0) === 7
                && (string) ($body['access_key'] ?? '') === 'ak'
                && (int) ($body['context']['order_id'] ?? 0) > 0
                && (int) ($body['context']['points_minor'] ?? -1) === 300;
        })->andReturn([
            'saga_instance_id' => '1',
            'idem_key' => 88_001,
            'flow_id' => 7,
            'status' => 40,
            'current_step_index' => 0,
            'retry_count' => 0,
            'last_error' => '',
            'context' => [
                'prepay' => ['stub' => true, 'amount_minor' => 700],
                'global_tx_id' => 'gtx-m',
                'idem_key' => 55_055,
                'tcc_idem_key' => 'ord:33:x',
            ],
            'step_runs' => [],
        ]);
        $this->app->instance(SagaCoordinatorClient::class, $saga);
        $this->app->forgetInstance(CheckoutOrchestrator::class);

        $result = app(CheckoutOrchestrator::class)->checkoutExistingOrder(33, $order, 300);

        $this->assertSame(700, (int) $result['prepay']['amount_minor']);
        $this->assertSame('gtx-m', $result['tid']);
        $this->assertSame('ord:33:x', $result['points_tcc_idem_key']);

        $fresh = $result['order']->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('gtx-m', $fresh->tid);
        $this->assertSame(55_055, (int) $fresh->tcc_idem_key);
    }

    public function test_checkout_throws_when_saga_omits_prepay(): void
    {
        config()->set('api_gw.base_url', 'http://foundation.local');
        config()->set('mall_agg.saga.flow_id', 7);
        config()->set('mall_agg.saga.access_key', 'ak');
        config()->set('mall_agg.tcc.flow_id', 1);
        config()->set('mall_agg.tcc.access_key', 'tcc');

        ProductPrice::query()->create([
            'pid' => 1,
            'price' => 10,
            'ct' => 1,
            'ut' => 1,
        ]);
        $order = app(OrderCommandService::class)->createDraftPendingOrder(1, [['product_id' => 1, 'quantity' => 1]]);

        $saga = Mockery::mock(SagaCoordinatorClient::class);
        $saga->shouldReceive('start')->once()->andReturn([
            'saga_instance_id' => '1',
            'idem_key' => 1,
            'flow_id' => 1,
            'status' => 40,
            'context' => [
                'global_tx_id' => 'x',
                'idem_key' => 1,
            ],
            'step_runs' => [],
        ]);
        $this->app->instance(SagaCoordinatorClient::class, $saga);
        $this->app->forgetInstance(CheckoutOrchestrator::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('prepay');

        app(CheckoutOrchestrator::class)->checkoutExistingOrder(1, $order, 0);
    }
}
