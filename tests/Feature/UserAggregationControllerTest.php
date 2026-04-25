<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Paganini\Capability\ProviderRegistry;
use Tests\TestCase;

class UserAggregationControllerTest extends TestCase
{
    public function test_me_returns_401_when_user_access_token_missing(): void
    {
        $response = $this->getJson('/api/user/me');

        $response->assertStatus(401)
            ->assertJsonPath('errorCode', 40101);
        $this->assertStringContainsString('Authentication required', (string) $response->json('message'));
    }

    public function test_me_returns_foundation_user_with_empty_business_plugins(): void
    {
        config()->set('api_gw.base_url', 'http://foundation.local');
        config()->set('mall_agg.foundation.base_url', 'http://foundation.local');
        config()->set('mall_agg.foundation.me_endpoint', '/api/user/me');
        config()->set('mall_agg.business_services', []);
        config()->set('mall_agg.execution.mode', 'serial');
        config()->set('mall_agg.degrade.strategy', 'mask_null');

        $this->app->forgetInstance(ProviderRegistry::class);

        Http::fake([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => 0,
                'data' => ['id' => 101, 'username' => 'mini'],
                'message' => '',
            ], 200),
        ]);

        $response = $this->withHeaders([
            'X-User-Access-Token' => 'token-abc',
            'X-Trace-Id' => 'trace-001',
        ])->getJson('/api/user/me');

        $response->assertOk()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.user.id', 101)
            ->assertJsonPath('data.biz', [])
            ->assertJsonPath('data.meta.degraded', false);
    }
}
