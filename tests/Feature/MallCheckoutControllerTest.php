<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CheckoutPhase;
use App\Enums\MallOrderStatus;
use App\Models\MallOrder;
use App\Models\ProductPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MallCheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('api_gw.base_url', 'http://foundation.local');
        config()->set('mall_agg.foundation.base_url', 'http://foundation.local');
        config()->set('mall_agg.foundation.me_endpoint', '/api/user/me');
        config()->set('mall_agg.saga.flow_id', 7);
        config()->set('mall_agg.saga.access_key', 'checkout-test-ak');
        config()->set('mall_agg.tcc.flow_id', 501);
        config()->set('mall_agg.tcc.access_key', 'tcc-ak');
    }

    private function fakeUserMe(int $userId, array $contextOverrides = []): void
    {
        $ctx = array_merge([
            'prepay' => ['stub' => true, 'amount_minor' => 50, 'status' => 'stub_await_payment'],
            'global_tx_id' => 'gtx-fe',
            'idem_key' => 77_002,
            'tcc_idem_key' => null,
        ], $contextOverrides);

        $sagaData = [
            'saga_instance_id' => '1',
            'idem_key' => 88_001,
            'flow_id' => 7,
            'status' => 40,
            'current_step_index' => 0,
            'retry_count' => 0,
            'last_error' => '',
            'context' => $ctx,
            'step_runs' => [],
        ];

        Http::fake(array_merge(
            [
                'http://foundation.local/api/saga/instances' => Http::response([
                    'errorCode' => 0,
                    'data' => $sagaData,
                    'message' => '',
                ], 200),
            ],
            [
                'http://foundation.local/api/user/me' => Http::response([
                    'errorCode' => 0,
                    'data' => ['id' => $userId, 'username' => 'buyer'],
                    'message' => '',
                ], 200),
            ]
        ));
    }

    public function test_checkout_requires_auth(): void
    {
        $this->postJson('/api/mall/checkout', ['order_id' => 1])
            ->assertStatus(401)
            ->assertJsonPath('errorCode', 40101);
    }

    public function test_checkout_returns_prepay_from_saga_and_merges_tcc_fields(): void
    {
        $this->fakeUserMe(42, [
            'prepay' => ['stub' => true, 'amount_minor' => 50],
            'tcc_idem_key' => 'ord:1:ab',
        ]);

        ProductPrice::query()->create([
            'pid' => 7,
            'price' => 100,
            'ct' => 1,
            'ut' => 1,
        ]);

        $create = $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/orders', [
            'lines' => [['product_id' => 7, 'quantity' => 1]],
        ]);
        $create->assertCreated();
        $orderId = (int) $create->json('data.id');

        $response = $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/checkout', [
            'order_id' => $orderId,
            'points_minor' => 0,
        ]);

        $response->assertCreated()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.order.status', MallOrderStatus::Pending->value)
            ->assertJsonPath('data.prepay.amount_minor', 50)
            ->assertJsonPath('data.tid', 'gtx-fe');

        $order = MallOrder::query()->find($orderId);
        $this->assertNotNull($order);
        $this->assertSame('gtx-fe', $order->tid);
        $this->assertSame(77_002, (int) $order->tcc_idem_key);
    }

    public function test_checkout_rejects_when_tcc_not_configured(): void
    {
        config()->set('mall_agg.tcc.flow_id', 0);

        $this->fakeUserMe(1);

        ProductPrice::query()->create([
            'pid' => 1,
            'price' => 50,
            'ct' => 1,
            'ut' => 1,
        ]);

        $orderId = (int) $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/orders', [
            'lines' => [['product_id' => 1, 'quantity' => 1]],
        ])->json('data.id');

        $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/checkout', [
            'order_id' => $orderId,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errorCode', 40001);
    }

    public function test_checkout_rejects_when_order_not_draft(): void
    {
        $this->fakeUserMe(1);

        ProductPrice::query()->create([
            'pid' => 1,
            'price' => 50,
            'ct' => 1,
            'ut' => 1,
        ]);

        $orderId = (int) $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/orders', [
            'lines' => [['product_id' => 1, 'quantity' => 1]],
        ])->json('data.id');

        MallOrder::query()->whereKey($orderId)->update(['checkout_phase' => CheckoutPhase::AwaitPayment->value]);

        $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/checkout', [
            'order_id' => $orderId,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errorCode', 40001);
    }

    public function test_checkout_returns_422_when_order_id_invalid(): void
    {
        $this->fakeUserMe(1);

        $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/checkout', [
            'order_id' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errorCode', 100);
    }

    public function test_checkout_returns_404_when_order_not_found(): void
    {
        $this->fakeUserMe(1);

        $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/checkout', [
            'order_id' => 99999,
        ])
            ->assertStatus(404)
            ->assertJsonPath('errorCode', 40401);
    }

    public function test_checkout_maps_saga_envelope_error_to_422(): void
    {
        Http::fake([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => 0,
                'data' => ['id' => 99, 'username' => 'buyer'],
                'message' => '',
            ], 200),
            'http://foundation.local/api/saga/instances' => Http::response([
                'errorCode' => 100,
                'message' => 'insufficient points',
                'data' => null,
            ], 200),
        ]);

        ProductPrice::query()->create([
            'pid' => 7,
            'price' => 50,
            'ct' => 1,
            'ut' => 1,
        ]);

        $orderId = (int) $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/orders', [
            'lines' => [['product_id' => 7, 'quantity' => 1]],
        ])->json('data.id');

        $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/checkout', [
            'order_id' => $orderId,
            'points_minor' => 30,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errorCode', 40001);
    }

    public function test_checkout_requires_prepay_in_saga_response(): void
    {
        Http::fake([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => 0,
                'data' => ['id' => 5, 'username' => 'buyer'],
                'message' => '',
            ], 200),
            'http://foundation.local/api/saga/instances' => Http::response([
                'errorCode' => 0,
                'data' => [
                    'idem_key' => 1,
                    'saga_instance_id' => '1',
                    'flow_id' => 1,
                    'status' => 40,
                    'current_step_index' => 0,
                    'retry_count' => 0,
                    'last_error' => '',
                    'context' => [
                        'global_tx_id' => 'x',
                        'idem_key' => 2,
                    ],
                    'step_runs' => [],
                ],
                'message' => '',
            ], 200),
        ]);

        ProductPrice::query()->create([
            'pid' => 3,
            'price' => 10,
            'ct' => 1,
            'ut' => 1,
        ]);

        $orderId = (int) $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/orders', [
            'lines' => [['product_id' => 3, 'quantity' => 1]],
        ])->json('data.id');

        $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/checkout', [
            'order_id' => $orderId,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errorCode', 40001);
    }
}
