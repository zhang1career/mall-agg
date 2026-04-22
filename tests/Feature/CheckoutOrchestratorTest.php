<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\InventoryOutboundContract;
use App\Contracts\PaymentOutboundContract;
use App\Enums\MallOrderStatus;
use App\Models\MallOrder;
use App\Models\MallPointsBalance;
use App\Models\ProductInventory;
use App\Models\ProductPrice;
use App\Services\mall\CheckoutOrchestrator;
use App\Services\mall\OrderCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * 结账编排：两步支付第二步；失败时补偿（库存 release、本地积分 cancel、TCC 协调器 HTTP cancel）。
 *
 * @group checkout-orchestrator
 * @group tcc
 */
final class CheckoutOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('mall_agg.checkout.use_coordinators', true);
        config()->set('mall_agg.outbound.inventory.base_url', '');
        config()->set('api_gw.base_url', 'http://foundation.local');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 协调器模式下的 TCC：prepay 前已成功 begin 全局事务，失败时必须 POST cancel。
     */
    public function test_prepay_failure_cancels_global_tcc_and_cancels_order(): void
    {
        config()->set('mall_agg.checkout.use_tcc_coordinator', true);
        config()->set('mall_agg.tcc.branch_meta_points_id', 501);

        Http::fake([
            'http://foundation.local/api/tcc/transactions/begin' => Http::response([
                'errorCode' => 0,
                'data' => [
                    'global_tx_id' => 'gtx-compensate-1',
                    'idem_key' => 66001,
                ],
                'message' => '',
            ], 200),
            'http://foundation.local/api/tcc/transactions/gtx-compensate-1/cancel' => Http::response([
                'errorCode' => 0,
                'data' => ['cancelled' => true],
                'message' => '',
            ], 200),
        ]);

        ProductPrice::query()->create([
            'pid' => 501,
            'price' => 30,
            'ct' => 1,
            'ut' => 1,
        ]);

        MallPointsBalance::query()->create([
            'uid' => 900,
            'balance_minor' => 1000,
            'ct' => 1,
            'ut' => 1,
        ]);

        $inventory = Mockery::mock(InventoryOutboundContract::class);
        $inventory->shouldReceive('reserve')->once()->andReturn(['reserve_id' => 'rev-tcc-1']);
        $inventory->shouldReceive('release')->once()->with('rev-tcc-1');
        $this->app->instance(InventoryOutboundContract::class, $inventory);
        $this->app->forgetInstance(OrderCommandService::class);
        $this->app->forgetInstance(CheckoutOrchestrator::class);

        $order = app(OrderCommandService::class)->createPendingOrderForCheckout(900, [['product_id' => 501, 'quantity' => 1]]);

        $payment = Mockery::mock(PaymentOutboundContract::class);
        $payment->shouldReceive('createPrepay')->once()->andThrow(new RuntimeException('prepay gateway down'));

        $this->app->instance(PaymentOutboundContract::class, $payment);
        $this->app->forgetInstance(CheckoutOrchestrator::class);

        try {
            app(CheckoutOrchestrator::class)->checkoutExistingOrder(900, $order, 25);
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('prepay gateway down', $e->getMessage());
        }

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'http://foundation.local/api/tcc/transactions/gtx-compensate-1/cancel';
        });

        $order = MallOrder::query()->first();
        $this->assertNotNull($order);
        $this->assertSame(MallOrderStatus::Cancelled, $order->status);
    }

    /**
     * 本地积分参与者路径：Try 已落库，prepay 失败时走参与方 cancel（非协调器 cancel）。
     */
    public function test_prepay_failure_restores_points_for_local_tcc_try(): void
    {
        config()->set('mall_agg.checkout.use_tcc_coordinator', false);
        config()->set('mall_agg.checkout.use_coordinators', false);

        ProductPrice::query()->create([
            'pid' => 502,
            'price' => 10,
            'ct' => 1,
            'ut' => 1,
        ]);
        ProductInventory::query()->create([
            'pid' => 502,
            'quantity' => 50,
            'ct' => 1,
            'ut' => 1,
        ]);

        MallPointsBalance::query()->create([
            'uid' => 901,
            'balance_minor' => 500,
            'ct' => 1,
            'ut' => 1,
        ]);

        $this->app->forgetInstance(OrderCommandService::class);
        $order = app(OrderCommandService::class)->createPendingOrderForCheckout(901, [['product_id' => 502, 'quantity' => 1]]);

        $payment = Mockery::mock(PaymentOutboundContract::class);
        $payment->shouldReceive('createPrepay')->once()->andThrow(new RuntimeException('prepay fail'));

        $this->app->instance(PaymentOutboundContract::class, $payment);
        $this->app->forgetInstance(CheckoutOrchestrator::class);

        try {
            app(CheckoutOrchestrator::class)->checkoutExistingOrder(901, $order, 5);
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('prepay fail', $e->getMessage());
        }

        $this->assertSame(
            500,
            (int) MallPointsBalance::query()->where('uid', 901)->value('balance_minor')
        );

        $order = MallOrder::query()->first();
        $this->assertNotNull($order);
        $this->assertSame(MallOrderStatus::Cancelled, $order->status);
    }

    /**
     * 无积分分支：仅释放外部预留。
     */
    public function test_prepay_failure_releases_inventory_when_no_points(): void
    {
        config()->set('mall_agg.checkout.use_tcc_coordinator', false);

        ProductPrice::query()->create([
            'pid' => 600,
            'price' => 5,
            'ct' => 1,
            'ut' => 1,
        ]);

        $inventory = Mockery::mock(InventoryOutboundContract::class);
        $inventory->shouldReceive('reserve')->once()->andReturn(['reserve_id' => 'rev-xyz']);
        $inventory->shouldReceive('release')->once()->with('rev-xyz');

        $payment = Mockery::mock(PaymentOutboundContract::class);
        $payment->shouldReceive('createPrepay')->once()->andThrow(new RuntimeException('prepay fail'));

        $this->app->instance(InventoryOutboundContract::class, $inventory);
        $this->app->instance(PaymentOutboundContract::class, $payment);
        $this->app->forgetInstance(OrderCommandService::class);
        $this->app->forgetInstance(CheckoutOrchestrator::class);

        $order = app(OrderCommandService::class)->createPendingOrderForCheckout(1, [['product_id' => 600, 'quantity' => 1]]);

        try {
            app(CheckoutOrchestrator::class)->checkoutExistingOrder(1, $order, 0);
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException) {
        }

        $order = MallOrder::query()->first();
        $this->assertNotNull($order);
        $this->assertSame(MallOrderStatus::Cancelled, $order->status);
    }

    public function test_create_prepay_uses_cash_after_points(): void
    {
        config()->set('mall_agg.checkout.use_tcc_coordinator', false);
        config()->set('mall_agg.checkout.use_coordinators', false);

        ProductPrice::query()->create([
            'pid' => 88,
            'price' => 1000,
            'ct' => 1,
            'ut' => 1,
        ]);
        ProductInventory::query()->create([
            'pid' => 88,
            'quantity' => 20,
            'ct' => 1,
            'ut' => 1,
        ]);

        MallPointsBalance::query()->create([
            'uid' => 33,
            'balance_minor' => 5000,
            'ct' => 1,
            'ut' => 1,
        ]);

        $this->app->forgetInstance(OrderCommandService::class);
        $order = app(OrderCommandService::class)->createPendingOrderForCheckout(33, [['product_id' => 88, 'quantity' => 1]]);

        $payment = Mockery::mock(PaymentOutboundContract::class);
        $payment->shouldReceive('createPrepay')
            ->once()
            ->withArgs(fn (int $oid, int $amt, int $u) => $oid === $order->id && $amt === 700 && $u === 33)
            ->andReturn(['stub' => true]);

        $this->app->instance(PaymentOutboundContract::class, $payment);
        $this->app->forgetInstance(CheckoutOrchestrator::class);

        $result = app(CheckoutOrchestrator::class)->checkoutExistingOrder(33, $order, 300);

        $this->assertSame(700, (int) $result['order']->cash_payable_minor);
        $this->assertSame(300, (int) $result['order']->points_deduct_minor);
    }
}
