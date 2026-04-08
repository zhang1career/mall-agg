<?php

namespace Tests\Feature;

use App\Models\ProductInventory;
use App\Models\ProductPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MallOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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

        $response = $this->withHeader('Authorization', 'Bearer tok')->postJson('/api/mall/orders', [
            'lines' => [['product_id' => 7, 'quantity' => 2]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_price', 200);

        $this->assertSame(1, (int) ProductInventory::query()->where('pid', 7)->value('quantity'));
    }
}
