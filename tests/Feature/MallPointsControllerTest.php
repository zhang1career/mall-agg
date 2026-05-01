<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MallPointsBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MallPointsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('api_gw.base_url', 'http://foundation.local');
        config()->set('mall_agg.foundation.base_url', 'http://foundation.local');
        config()->set('mall_agg.foundation.me_endpoint', '/api/user/me');
    }

    public function test_points_requires_auth(): void
    {
        $this->getJson('/api/mall/points')->assertStatus(401)->assertJsonPath('errorCode', 200);
    }

    public function test_points_returns_zero_when_no_balance_row(): void
    {
        Http::fake([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => 0,
                'data' => ['id' => 7, 'username' => 'u'],
                'message' => '',
            ], 200),
        ]);

        $this->withHeader('X-User-Access-Token', 'tok')->getJson('/api/mall/points')
            ->assertOk()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.balance_minor', 0);
    }

    public function test_points_returns_balance_minor(): void
    {
        Http::fake([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => 0,
                'data' => ['id' => 88, 'username' => 'u'],
                'message' => '',
            ], 200),
        ]);

        MallPointsBalance::query()->create([
            'uid' => 88,
            'balance_minor' => 12_345,
            'ct' => 1,
            'ut' => 1,
        ]);

        $this->withHeader('X-User-Access-Token', 'tok')->getJson('/api/mall/points')
            ->assertOk()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.balance_minor', 12_345);
    }
}
