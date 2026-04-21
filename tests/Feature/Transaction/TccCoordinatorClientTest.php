<?php

declare(strict_types=1);

namespace Tests\Feature\Transaction;

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
    }

    public function test_begin_posts_transactions_begin_and_returns_global_tx_id(): void
    {
        Http::fake([
            'http://gw.test/api/tcc/transactions/begin' => Http::response([
                'errorCode' => 0,
                'data' => [
                    'global_tx_id' => 'gtx-abc',
                    'idem_key' => 55,
                ],
                'message' => '',
            ], 200),
        ]);

        $out = app(TccCoordinatorClient::class)->begin([
            'branches' => [['branch_meta_id' => 1, 'payload' => []]],
            'auto_confirm' => false,
        ]);

        $this->assertSame('gtx-abc', $out['global_tx_id']);
        Http::assertSent(fn ($request) => $request->url() === 'http://gw.test/api/tcc/transactions/begin'
            && $request->method() === 'POST');
    }

    public function test_detail_get_accepts_global_tx_id_query(): void
    {
        Http::fake([
            'http://gw.test/api/tcc/transactions/detail*' => Http::response([
                'errorCode' => 0,
                'data' => ['status' => 'pending'],
                'message' => '',
            ], 200),
        ]);

        $detail = app(TccCoordinatorClient::class)->detail(null, 'gtx-abc');

        $this->assertSame(['status' => 'pending'], $detail);
        Http::assertSent(function ($request) {
            $u = parse_url((string) $request->url());

            return $request->method() === 'GET'
                && ($u['path'] ?? '') === '/api/tcc/transactions/detail'
                && str_contains($u['query'] ?? '', 'global_tx_id=gtx-abc');
        });
    }

    public function test_confirm_posts_to_transactions_global_id_confirm(): void
    {
        Http::fake([
            'http://gw.test/api/tcc/transactions/gtx-z/confirm' => Http::response([
                'errorCode' => 0,
                'data' => ['ok' => true],
                'message' => '',
            ], 200),
        ]);

        $data = app(TccCoordinatorClient::class)->confirm('gtx-z');

        $this->assertSame(['ok' => true], $data);
        Http::assertSent(fn ($req) => $req->method() === 'POST'
            && $req->url() === 'http://gw.test/api/tcc/transactions/gtx-z/confirm');
    }

    public function test_cancel_posts_to_transactions_global_id_cancel(): void
    {
        Http::fake([
            'http://gw.test/api/tcc/transactions/gtx-z/cancel' => Http::response([
                'errorCode' => 0,
                'data' => ['cancelled' => true],
                'message' => '',
            ], 200),
        ]);

        $data = app(TccCoordinatorClient::class)->cancel('gtx-z');

        $this->assertSame(['cancelled' => true], $data);
        Http::assertSent(fn ($req) => $req->method() === 'POST'
            && $req->url() === 'http://gw.test/api/tcc/transactions/gtx-z/cancel');
    }

    public function test_begin_nonzero_error_code_maps_to_exception_via_envelope(): void
    {
        Http::fake([
            'http://gw.test/api/tcc/transactions/begin' => Http::response([
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
