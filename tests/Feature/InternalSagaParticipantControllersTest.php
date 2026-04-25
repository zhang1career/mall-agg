<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\InventoryOutboundContract;
use App\Enums\CheckoutPhase;
use App\Models\ProductPrice;
use App\Services\mall\OrderCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class InternalSagaParticipantControllersTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_inventory_action_reserves_remote_and_compensate_releases(): void
    {
        $this->mock(InventoryOutboundContract::class, function ($mock): void {
            $mock->shouldReceive('reserve')->once()->andReturn(['reserve_id' => 'rev-remote-1']);
            $mock->shouldReceive('release')->once()->with('rev-remote-1');
        });

        ProductPrice::query()->create([
            'pid' => 3,
            'price' => 10,
            'ct' => 1,
            'ut' => 1,
        ]);

        $idem = 'saga-inv-'.bin2hex(random_bytes(4));

        $this->postJson('/internal/inventory/action', [
            'payload' => [
                'uid' => 1,
                'lines' => [['product_id' => 3, 'quantity' => 2]],
                'saga_step_idem_key' => $idem,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.mode', 'remote')
            ->assertJsonPath('data.inventory_token', 'rev-remote-1');

        $this->postJson('/internal/inventory/compensate', [
            'payload' => ['inventory_token' => 'rev-remote-1'],
        ])
            ->assertOk()
            ->assertJsonPath('errorCode', 0);
    }

    public function test_order_action_binds_draft_order_to_inventory_token(): void
    {
        $this->mock(InventoryOutboundContract::class, function ($mock): void {
            $mock->shouldReceive('reserve')->once()->andReturn(['reserve_id' => 'rev-bind-1']);
            $mock->shouldReceive('release')->never();
        });

        ProductPrice::query()->create([
            'pid' => 4,
            'price' => 20,
            'ct' => 1,
            'ut' => 1,
        ]);

        $order = app(OrderCommandService::class)->createDraftPendingOrder(7, [['product_id' => 4, 'quantity' => 1]]);

        $invIdem = 'inv-'.bin2hex(random_bytes(3));
        $tryInv = $this->postJson('/internal/inventory/action', [
            'payload' => [
                'uid' => 7,
                'lines' => [['product_id' => 4, 'quantity' => 1]],
                'saga_step_idem_key' => $invIdem,
            ],
        ]);
        $token = (string) $tryInv->json('data.inventory_token');

        $ordIdem = 'ord-'.bin2hex(random_bytes(3));
        $this->postJson('/internal/order/action', [
            'payload' => [
                'uid' => 7,
                'order_id' => $order->id,
                'inventory_token' => $token,
                'saga_step_idem_key' => $ordIdem,
                'saga_idem_key' => 42_001,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.order_id', $order->id);

        $order->refresh();
        $this->assertTrue($order->ext_inventory);
        $this->assertSame('rev-bind-1', $order->ext_id);
        $this->assertSame(42_001, (int) $order->saga_idem_key);
    }

    public function test_pay_action_creates_prepay_stub(): void
    {
        $this->mock(InventoryOutboundContract::class, function ($mock): void {
            $mock->shouldReceive('reserve')->once()->andReturn(['reserve_id' => 'rev-pay-1']);
        });

        ProductPrice::query()->create([
            'pid' => 5,
            'price' => 100,
            'ct' => 1,
            'ut' => 1,
        ]);

        $order = app(OrderCommandService::class)->createDraftPendingOrder(3, [['product_id' => 5, 'quantity' => 1]]);

        $inv = $this->postJson('/internal/inventory/action', [
            'payload' => [
                'uid' => 3,
                'lines' => [['product_id' => 5, 'quantity' => 1]],
                'saga_step_idem_key' => 'inv-pay-'.bin2hex(random_bytes(2)),
            ],
        ]);
        $tok = (string) $inv->json('data.inventory_token');

        $this->postJson('/internal/order/action', [
            'payload' => [
                'uid' => 3,
                'order_id' => $order->id,
                'inventory_token' => $tok,
                'saga_step_idem_key' => 'ord-pay-'.bin2hex(random_bytes(2)),
                'saga_idem_key' => 99_001,
            ],
        ])->assertOk();

        $order->refresh();
        $order->cash_payable_minor = (int) $order->total_price;
        $order->save();

        $this->postJson('/internal/pay/action', [
            'payload' => [
                'order_id' => $order->id,
                'saga_step_idem_key' => 'pay-'.bin2hex(random_bytes(2)),
            ],
        ])
            ->assertOk()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.prepay.status', 'stub_await_payment');

        $order->refresh();
        $this->assertSame(CheckoutPhase::AwaitPayment, $order->checkout_phase);
    }

    public function test_pay_try_matches_action_for_tcc_envelope(): void
    {
        ProductPrice::query()->create([
            'pid' => 6,
            'price' => 200,
            'ct' => 1,
            'ut' => 1,
        ]);

        $order = app(OrderCommandService::class)->createDraftPendingOrder(4, [['product_id' => 6, 'quantity' => 1]]);
        $order->cash_payable_minor = (int) $order->total_price;
        $order->save();

        $idem = 'tcc-branch-'.bin2hex(random_bytes(4));
        $this->postJson('/internal/pay/try', [
            'idempotency_key' => $idem,
            'payload' => ['order_id' => $order->id],
        ])
            ->assertOk()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.prepay.status', 'stub_await_payment');

        $this->postJson('/internal/pay/try', [
            'idempotency_key' => $idem,
            'payload' => ['order_id' => $order->id],
        ])
            ->assertOk()
            ->assertJsonPath('errorCode', 0);
    }
}
