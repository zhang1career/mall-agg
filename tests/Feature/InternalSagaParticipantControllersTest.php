<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MallOrder;
use App\Models\ProductInventory;
use App\Models\ProductPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InternalSagaParticipantControllersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('mall_agg.checkout.use_saga_coordinators', false);
    }

    public function test_inventory_action_compensate_round_trip_local(): void
    {
        ProductPrice::query()->create([
            'pid' => 3,
            'price' => 10,
            'ct' => 1,
            'ut' => 1,
        ]);
        ProductInventory::query()->create([
            'pid' => 3,
            'quantity' => 5,
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
            ->assertJsonPath('data.mode', 'local');

        $this->assertSame(3, (int) ProductInventory::query()->where('pid', 3)->value('quantity'));

        $token = (string) $this->postJson('/internal/inventory/action', [
            'payload' => [
                'uid' => 1,
                'lines' => [['product_id' => 3, 'quantity' => 2]],
                'saga_step_idem_key' => $idem,
            ],
        ])->json('data.inventory_token');

        $this->assertStringStartsWith('localhold:', $token);
        $this->assertSame(3, (int) ProductInventory::query()->where('pid', 3)->value('quantity'));

        $this->postJson('/internal/inventory/compensate', [
            'payload' => ['inventory_token' => $token],
        ])
            ->assertOk()
            ->assertJsonPath('errorCode', 0);

        $this->assertSame(5, (int) ProductInventory::query()->where('pid', 3)->value('quantity'));
    }

    public function test_order_action_after_inventory_local_then_compensate(): void
    {
        ProductPrice::query()->create([
            'pid' => 4,
            'price' => 20,
            'ct' => 1,
            'ut' => 1,
        ]);
        ProductInventory::query()->create([
            'pid' => 4,
            'quantity' => 10,
            'ct' => 1,
            'ut' => 1,
        ]);

        $invIdem = 'inv-'.bin2hex(random_bytes(3));
        $tryInv = $this->postJson('/internal/inventory/action', [
            'payload' => [
                'uid' => 7,
                'lines' => [['product_id' => 4, 'quantity' => 1]],
                'saga_step_idem_key' => $invIdem,
            ],
        ]);
        $tryInv->assertOk()->assertJsonPath('errorCode', 0);
        $token = (string) $tryInv->json('data.inventory_token');

        $ordIdem = 'ord-'.bin2hex(random_bytes(3));
        $tryOrd = $this->postJson('/internal/order/action', [
            'payload' => [
                'uid' => 7,
                'lines' => [['product_id' => 4, 'quantity' => 1]],
                'inventory_token' => $token,
                'saga_step_idem_key' => $ordIdem,
                'saga_idem_key' => null,
            ],
        ]);
        $tryOrd->assertOk()->assertJsonPath('errorCode', 0);
        $orderId = (int) $tryOrd->json('data.order_id');
        $this->assertGreaterThan(0, $orderId);

        $this->postJson('/internal/inventory/compensate', [
            'payload' => ['inventory_token' => $token],
        ])->assertOk();

        $this->postJson('/internal/order/compensate', [
            'payload' => ['order_id' => $orderId],
        ])->assertOk();

        $order = MallOrder::query()->find($orderId);
        $this->assertNotNull($order);
        $this->assertSame(2, (int) $order->status->value);
    }

    public function test_pay_action_uses_stub_payment(): void
    {
        ProductPrice::query()->create([
            'pid' => 5,
            'price' => 100,
            'ct' => 1,
            'ut' => 1,
        ]);
        ProductInventory::query()->create([
            'pid' => 5,
            'quantity' => 2,
            'ct' => 1,
            'ut' => 1,
        ]);

        $invIdem = 'inv-p-'.bin2hex(random_bytes(2));
        $inv = $this->postJson('/internal/inventory/action', [
            'payload' => [
                'uid' => 3,
                'lines' => [['product_id' => 5, 'quantity' => 1]],
                'saga_step_idem_key' => $invIdem,
            ],
        ]);
        $tok = (string) $inv->json('data.inventory_token');

        $ordIdem = 'ord-p-'.bin2hex(random_bytes(2));
        $ord = $this->postJson('/internal/order/action', [
            'payload' => [
                'uid' => 3,
                'lines' => [['product_id' => 5, 'quantity' => 1]],
                'inventory_token' => $tok,
                'saga_step_idem_key' => $ordIdem,
            ],
        ]);
        $orderId = (int) $ord->json('data.order_id');

        $payIdem = 'pay-'.bin2hex(random_bytes(2));
        $this->postJson('/internal/pay/action', [
            'payload' => [
                'order_id' => $orderId,
                'saga_step_idem_key' => $payIdem,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.status', 'stub_await_payment');
    }
}
