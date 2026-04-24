<?php

declare(strict_types=1);

namespace Tests\Feature\Transaction;

use App\Enums\TccCancelReason;
use App\Services\Transaction\TccCoordinatorClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * TCC 协调器出站：全局事务 begin / confirm / cancel / detail 与 HTTP 契约。
 *
 * @group tcc
 */
final class TccCoordinatorClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('api_gw.base_url', 'http://gw.test');
        config()->set('mall_agg.tcc.timeout_seconds', 5);
        config()->set('mall_agg.tcc.flow_id', 42);
    }

    public function test_begin_posts_api_tcc_tx_and_returns_global_tx_id(): void
    {
        Http::fake([
            'http://gw.test/api/tcc/tx' => Http::response([
                'errorCode' => 0,
                'data' => [
                    'global_tx_id' => 'gtx-abc',
                    'idem_key' => 55,
                ],
                'message' => '',
            ], 200),
        ]);

        $out = app(TccCoordinatorClient::class)->begin([
            'branches' => [['branch_index' => 0, 'payload' => []]],
            'auto_confirm' => false,
        ]);

        $this->assertSame('gtx-abc', $out['global_tx_id']);
        Http::assertSent(function ($request) {
            if ($request->url() !== 'http://gw.test/api/tcc/tx' || $request->method() !== 'POST') {
                return false;
            }
            $data = $request->data();

            return (int) ($data['biz_id'] ?? 0) === 42
                && isset($data['branches'][0]['branch_index']);
        });
    }

    public function test_detail_get_uses_idem_key_path(): void
    {
        Http::fake([
            'http://gw.test/api/tcc/tx/9876543210987654321' => Http::response([
                'errorCode' => 0,
                'data' => ['status' => 'pending'],
                'message' => '',
            ], 200),
        ]);

        $detail = app(TccCoordinatorClient::class)->detail('9876543210987654321');

        $this->assertSame(['status' => 'pending'], $detail);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->url() === 'http://gw.test/api/tcc/tx/9876543210987654321');
    }

    public function test_confirm_posts_to_tx_idem_key_confirm(): void
    {
        Http::fake([
            'http://gw.test/api/tcc/tx/9001/confirm' => Http::response([
                'errorCode' => 0,
                'data' => ['ok' => true],
                'message' => '',
            ], 200),
        ]);

        $data = app(TccCoordinatorClient::class)->confirm('9001');

        $this->assertSame(['ok' => true], $data);
        Http::assertSent(fn ($req) => $req->method() === 'POST'
            && $req->url() === 'http://gw.test/api/tcc/tx/9001/confirm');
    }

    public function test_cancel_posts_to_tx_idem_key_cancel_with_reason(): void
    {
        Http::fake([
            'http://gw.test/api/tcc/tx/9001/cancel' => Http::response([
                'errorCode' => 0,
                'data' => ['cancelled' => true],
                'message' => '',
            ], 200),
        ]);

        $data = app(TccCoordinatorClient::class)->cancel('9001', TccCancelReason::OrderClosed);

        $this->assertSame(['cancelled' => true], $data);
        Http::assertSent(function ($req) {
            if ($req->method() !== 'POST' || $req->url() !== 'http://gw.test/api/tcc/tx/9001/cancel') {
                return false;
            }
            $body = $req->data();

            return ($body['cancel_reason'] ?? null) === 10;
        });
    }

    public function test_begin_nonzero_error_code_maps_to_exception_via_envelope(): void
    {
        Http::fake([
            'http://gw.test/api/tcc/tx' => Http::response([
                'errorCode' => 400,
                'message' => 'invalid branch',
                'data' => [],
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tcc begin: invalid branch (errorCode=400)');

        app(TccCoordinatorClient::class)->begin(['branches' => []]);
    }
}
