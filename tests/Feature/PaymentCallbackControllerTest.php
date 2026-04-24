<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CheckoutPhase;
use App\Enums\MallOrderStatus;
use App\Models\MallOrder;
use App\Models\ProductInventory;
use App\Models\ProductPrice;
use App\Services\mall\OrderCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PaymentCallbackControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_marks_order_paid_when_no_points_or_tcc(): void
    {
        ProductPrice::query()->create([
            'pid' => 1,
            'price' => 99,
            'ct' => 1,
            'ut' => 1,
        ]);
        ProductInventory::query()->create([
            'pid' => 1,
            'quantity' => 5,
            'ct' => 1,
            'ut' => 1,
        ]);

        config()->set('mall_agg.checkout.use_saga_coordinators', false);

        $order = app(OrderCommandService::class)
            ->createPendingOrderForCheckout(7, [['product_id' => 1, 'quantity' => 1]]);
        $this->assertSame(MallOrderStatus::Pending, $order->status);
        $order->checkout_phase = CheckoutPhase::AwaitPayment;
        $order->cash_payable_minor = (int) $order->total_price;
        $order->save();

        $this->postJson('/api/mall/payment/callback', [
            'order_id' => $order->id,
            'status' => 'paid',
        ])->assertOk()->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.status', MallOrderStatus::Paid->value);

        $fresh = MallOrder::query()->find($order->id);
        $this->assertNotNull($fresh);
        $this->assertSame(MallOrderStatus::Paid, $fresh->status);
        $this->assertSame(CheckoutPhase::Completed, $fresh->checkout_phase);
    }

    public function test_callback_is_idempotent_when_already_paid(): void
    {
        ProductPrice::query()->create([
            'pid' => 2,
            'price' => 10,
            'ct' => 1,
            'ut' => 1,
        ]);
        ProductInventory::query()->create([
            'pid' => 2,
            'quantity' => 1,
            'ct' => 1,
            'ut' => 1,
        ]);
        config()->set('mall_agg.checkout.use_saga_coordinators', false);

        $order = app(OrderCommandService::class)
            ->createPendingOrderForCheckout(8, [['product_id' => 2, 'quantity' => 1]]);
        $order->checkout_phase = CheckoutPhase::AwaitPayment;
        $order->cash_payable_minor = (int) $order->total_price;
        $order->save();

        $this->postJson('/api/mall/payment/callback', [
            'order_id' => $order->id,
            'status' => 'paid',
        ])->assertOk();

        $this->postJson('/api/mall/payment/callback', [
            'order_id' => $order->id,
            'status' => 'paid',
        ])->assertOk()->assertJsonPath('errorCode', 0);
    }

    public function test_callback_rejects_when_callback_token_mismatch(): void
    {
        config()->set('mall_agg.payment.callback_token', 'secret-cb');

        $this->postJson('/api/mall/payment/callback', [
            'order_id' => 1,
            'status' => 'paid',
        ], ['X-Payment-Callback-Token' => 'wrong'])
            ->assertStatus(403)
            ->assertJsonPath('errorCode', 40301);
    }
}
