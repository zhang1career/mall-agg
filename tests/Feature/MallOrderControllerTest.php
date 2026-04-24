<?php

namespace Tests\Feature;

use App\Contracts\InventoryOutboundContract;
use App\Models\MallOrder;
use App\Models\ProductInventory;
use App\Models\ProductPrice;
use App\Services\mall\OrderCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MallOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('api_gw.base_url', 'http://foundation.local');
        config()->set('mall_agg.foundation.base_url', 'http://foundation.local');
        config()->set('mall_agg.foundation.me_endpoint', '/api/user/me');
    }

    public function test_create_order_requires_auth(): void
    {
        $response = $this->postJson('/api/mall/orders', [
            'lines' => [['product_id' => 1, 'quantity' => 1]],
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('errorCode', 40101);
    }

    public function test_create_order_places_pending_order_and_decrements_stock(): void
    {
        Http::fake([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => 0,
                'data' => ['id' => 42, 'username' => 'buyer'],
                'message' => '',
            ], 200),
        ]);

        ProductPrice::query()->create([
            'pid' => 7,
            'price' => 100,
            'ct' => 1,
            'ut' => 1,
        ]);
        ProductInventory::query()->create([
            'pid' => 7,
            'quantity' => 3,
            'ct' => 1,
            'ut' => 1,
        ]);

        $response = $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/orders', [
            'lines' => [['product_id' => 7, 'quantity' => 2]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.status', 0)
            ->assertJsonPath('data.total_price', 200);

        $this->assertSame(1, (int) ProductInventory::query()->where('pid', 7)->value('quantity'));
    }

    public function test_create_order_uses_external_reserve_when_coordinators_enabled(): void
    {
        config()->set('mall_agg.checkout.use_saga_coordinators', true);
        config()->set('mall_agg.saga.flow_id', 7);
        config()->set('mall_agg.saga.access_key', 'order-test-ak');

        Http::fake(array_merge(
            $this->fakeSagaCoordinatorStartHttp(33_011, 900),
            [
                'http://foundation.local/api/user/me' => Http::response([
                    'errorCode' => 0,
                    'data' => ['id' => 99, 'username' => 'buyer'],
                    'message' => '',
                ], 200),
            ]
        ));

        ProductPrice::query()->create([
            'pid' => 11,
            'price' => 25,
            'ct' => 1,
            'ut' => 1,
        ]);

        $response = $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/orders', [
            'lines' => [['product_id' => 11, 'quantity' => 2]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.total_price', 50);

        $this->assertTrue((bool) $response->json('data.ext_inventory'));
        $this->assertNotSame('', (string) $response->json('data.ext_id'));

        $order = MallOrder::query()->where('uid', 99)->first();
        $this->assertNotNull($order);
        $this->assertSame(33_011, (int) $order->saga_idem_key);
    }

    public function test_create_order_releases_reserve_when_saga_start_fails(): void
    {
        config()->set('mall_agg.checkout.use_saga_coordinators', true);
        config()->set('mall_agg.saga.flow_id', 7);
        config()->set('mall_agg.saga.access_key', 'ak');

        Http::fake([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => 0,
                'data' => ['id' => 12, 'username' => 'buyer'],
                'message' => '',
            ], 200),
            'http://foundation.local/api/saga/instances' => Http::response('err', 500),
        ]);

        $this->mock(InventoryOutboundContract::class, function ($mock): void {
            $mock->shouldReceive('reserve')->once()->andReturn(['reserve_id' => 'rev-saga-fail']);
            $mock->shouldReceive('release')->once()->with('rev-saga-fail');
        });
        $this->app->forgetInstance(OrderCommandService::class);

        ProductPrice::query()->create([
            'pid' => 21,
            'price' => 10,
            'ct' => 1,
            'ut' => 1,
        ]);

        $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/orders', [
            'lines' => [['product_id' => 21, 'quantity' => 1]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errorCode', 40001);

        $this->assertSame(0, (int) MallOrder::query()->count());
    }
}
