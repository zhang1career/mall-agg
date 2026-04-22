<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CheckoutPhase;
use App\Enums\MallOrderStatus;
use App\Enums\PointsHoldState;
use App\Models\MallOrder;
use App\Models\MallPointsBalance;
use App\Models\PointsFlow;
use App\Models\ProductInventory;
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
        config()->set('mall_agg.checkout.use_coordinators', true);
        config()->set('mall_agg.checkout.use_tcc_coordinator', false);
    }

    private function fakeUserMe(int $userId): void
    {
        Http::fake([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => 0,
                'data' => ['id' => $userId, 'username' => 'buyer'],
                'message' => '',
            ], 200),
        ]);
    }

    public function test_checkout_requires_auth(): void
    {
        $response = $this->postJson('/api/mall/checkout', [
            'order_id' => 1,
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('errorCode', 40101);
    }

    public function test_checkout_creates_external_order_and_freezes_points(): void
    {
        $this->fakeUserMe(42);

        ProductPrice::query()->create([
            'pid' => 7,
            'price' => 100,
            'ct' => 1,
            'ut' => 1,
        ]);

        MallPointsBalance::query()->create([
            'uid' => 42,
            'balance_minor' => 10_000,
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
            'points_minor' => 100,
        ]);

        $response->assertCreated()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.order.status', MallOrderStatus::Pending->value)
            ->assertJsonPath('data.order.ext_inventory', true)
            ->assertJsonPath('data.order.checkout_phase', CheckoutPhase::AwaitPayment->value)
            ->assertJsonPath('data.order.points_deduct_minor', 100)
            ->assertJsonPath('data.order.cash_payable_minor', 0)
            ->assertJsonPath('data.prepay.amount_minor', 0);

        $this->assertSame(9900, (int) MallPointsBalance::query()->where('uid', 42)->value('balance_minor'));

        $hold = PointsFlow::query()->where('uid', 42)->first();
        $this->assertNotNull($hold);
        $this->assertSame(100, (int) $hold->amount_minor);
        $this->assertSame(PointsHoldState::TrySucceeded, $hold->state);
    }

    public function test_checkout_works_when_coordinator_disabled_two_step(): void
    {
        config()->set('mall_agg.checkout.use_coordinators', false);

        $this->fakeUserMe(1);

        ProductPrice::query()->create([
            'pid' => 1,
            'price' => 50,
            'ct' => 1,
            'ut' => 1,
        ]);
        ProductInventory::query()->create([
            'pid' => 1,
            'quantity' => 5,
            'ct' => 1,
            'ut' => 1,
        ]);

        $create = $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/orders', [
            'lines' => [['product_id' => 1, 'quantity' => 1]],
        ]);
        $create->assertCreated();
        $orderId = (int) $create->json('data.id');

        $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/checkout', [
            'order_id' => $orderId,
        ])
            ->assertCreated()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.order.cash_payable_minor', 50)
            ->assertJsonPath('data.prepay.amount_minor', 50);
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

    public function test_checkout_rejects_insufficient_points(): void
    {
        $this->fakeUserMe(99);

        ProductPrice::query()->create([
            'pid' => 7,
            'price' => 50,
            'ct' => 1,
            'ut' => 1,
        ]);

        MallPointsBalance::query()->create([
            'uid' => 99,
            'balance_minor' => 10,
            'ct' => 1,
            'ut' => 1,
        ]);

        $create = $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/orders', [
            'lines' => [['product_id' => 7, 'quantity' => 1]],
        ]);
        $orderId = (int) $create->json('data.id');

        $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/checkout', [
            'order_id' => $orderId,
            'points_minor' => 30,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errorCode', 40001);

        $this->assertSame(10, (int) MallPointsBalance::query()->where('uid', 99)->value('balance_minor'));
    }

    public function test_checkout_with_tcc_coordinator_calls_begin_and_sets_order_tid(): void
    {
        config()->set('mall_agg.checkout.use_tcc_coordinator', true);
        config()->set('mall_agg.tcc.branch_meta_points_id', 501);

        Http::fake([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => 0,
                'data' => ['id' => 55, 'username' => 'u'],
                'message' => '',
            ], 200),
            'http://foundation.local/api/tcc/transactions/begin' => Http::response([
                'errorCode' => 0,
                'data' => [
                    'global_tx_id' => 'gtx-coord-99',
                    'idem_key' => 77_001,
                ],
                'message' => '',
            ], 200),
        ]);

        ProductPrice::query()->create([
            'pid' => 8,
            'price' => 20,
            'ct' => 1,
            'ut' => 1,
        ]);

        MallPointsBalance::query()->create([
            'uid' => 55,
            'balance_minor' => 5000,
            'ct' => 1,
            'ut' => 1,
        ]);

        $create = $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/orders', [
            'lines' => [['product_id' => 8, 'quantity' => 1]],
        ]);
        $orderId = (int) $create->json('data.id');

        $response = $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/mall/checkout', [
            'order_id' => $orderId,
            'points_minor' => 5,
        ]);

        $response->assertCreated()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.tid', 'gtx-coord-99')
            ->assertJsonPath('data.order.cash_payable_minor', 15)
            ->assertJsonPath('data.prepay.amount_minor', 15);

        $idem = $response->json('data.points_tcc_idem_key');
        $this->assertIsString($idem);
        $this->assertStringStartsWith('ord:', (string) $idem);

        $order = MallOrder::query()->find($orderId);
        $this->assertNotNull($order);
        $this->assertSame('gtx-coord-99', $order->tid);
        $this->assertSame(77_001, $order->tcc_idem_key);

        $this->assertSame(5000, (int) MallPointsBalance::query()->where('uid', 55)->value('balance_minor'));
        $this->assertSame(0, (int) PointsFlow::query()->count());

        Http::assertSent(function ($request) {
            if ($request->url() !== 'http://foundation.local/api/tcc/transactions/begin') {
                return false;
            }
            $body = $request->data();
            $branches = $body['branches'] ?? null;

            return is_array($branches)
                && isset($branches[0]['branch_meta_id'])
                && (int) $branches[0]['branch_meta_id'] === 501
                && isset($branches[0]['payload']['tcc_idem_key']);
        });
    }
}
