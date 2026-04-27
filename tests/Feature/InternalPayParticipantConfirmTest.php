<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class InternalPayParticipantConfirmTest extends TestCase
{
    public function test_pay_confirm_requires_idem_key(): void
    {
        $this->postJson('/internal/pay/confirm', [
            'payload' => [
                'order_id' => 1,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('errorCode', 100);
    }

    public function test_pay_confirm_posts_to_foundation_tcc_confirm(): void
    {
        config()->set('api_gw.base_url', 'http://serv-fd.test');
        config()->set('mall_agg.foundation.base_url', 'http://serv-fd.test');
        config()->set('mall_agg.tcc.timeout_seconds', 5);

        Http::fake([
            'http://serv-fd.test/api/tcc/tx/9001/confirm' => Http::response([
                'errorCode' => 0,
                'data' => ['global_tx_id' => 'gtx-1'],
                'message' => '',
            ], 200),
        ]);

        $this->postJson('/internal/pay/confirm', [
            'payload' => [
                'order_id' => 42,
                'idem_key' => '9001',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.order_id', 42)
            ->assertJsonPath('data.tcc.global_tx_id', 'gtx-1');

        Http::assertSent(fn ($req) => $req->method() === 'POST'
            && $req->url() === 'http://serv-fd.test/api/tcc/tx/9001/confirm');
    }
}
