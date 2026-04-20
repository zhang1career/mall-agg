<?php

namespace Tests\Feature;

use App\Models\ProductPrice;
use App\Services\mall\serv_fd\CmsProductClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MallProductControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('api_gw.base_url', 'http://serv-fd.test');
        config()->set('api_gw.cms.cms_url', '/api/cms/');
        config()->set('api_gw.cms.content_route', 'product');
        config()->set('mall_agg.foundation.base_url', 'http://serv-fd.test');
        $this->app->forgetInstance(CmsProductClient::class);
    }

    public function test_product_list_returns_prices_from_local_table(): void
    {
        Http::fake([
            'http://serv-fd.test/api/cms/product*' => Http::response([
                'errorCode' => 0,
                'message' => '',
                'data' => [
                    'items' => [
                        [
                            'id' => 1,
                            'content_type' => 'product',
                            'title' => 'A',
                        ],
                    ],
                    'pagination' => [
                        'total' => 1,
                        'per_page' => 15,
                        'current_page' => 1,
                        'last_page' => 1,
                    ],
                ],
            ], 200),
        ]);

        ProductPrice::query()->create([
            'pid' => 1,
            'price' => 1999,
            'ct' => 1,
            'ut' => 1,
        ]);

        $response = $this->getJson('/api/mall/products');

        $response->assertOk()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.items.0.price', 1999)
            ->assertJsonPath('data.items.0.title', 'A');
    }
}
