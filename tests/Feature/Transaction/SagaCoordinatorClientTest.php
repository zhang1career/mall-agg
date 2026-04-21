<?php

declare(strict_types=1);

namespace Tests\Feature\Transaction;

use App\Services\Transaction\CoordinatorEnvelope;
use App\Services\Transaction\SagaCoordinatorClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Saga 协调器出站契约：路径、JSON 信封 ({@see CoordinatorEnvelope})、失败语义。
 *
 * @group saga
 */
final class SagaCoordinatorClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('api_gw.base_url', 'http://gw.test');
        config()->set('mall_agg.saga.timeout_seconds', 5);
    }

    public function test_start_posts_expected_path_and_returns_data_envelope(): void
    {
        Http::fake([
            'http://gw.test/api/saga/instances/start' => Http::response([
                'errorCode' => 0,
                'data' => [
                    'saga_instance_id' => 900,
                    'idem_key' => 12001,
                ],
                'message' => '',
            ], 200),
        ]);

        $client = app(SagaCoordinatorClient::class);
        $out = $client->start([
            'access_key' => 'k',
            'flow_id' => 7,
            'context' => ['x' => 1],
        ]);

        $this->assertSame(900, $out['saga_instance_id']);
        Http::assertSent(function ($request) {
            return $request->url() === 'http://gw.test/api/saga/instances/start'
                && $request->method() === 'POST'
                && ($request->data()['flow_id'] ?? null) === 7;
        });
    }

    public function test_detail_get_by_idem_key_queries_instances_detail(): void
    {
        Http::fake([
            'http://gw.test/api/saga/instances/detail*' => Http::response([
                'errorCode' => 0,
                'data' => ['state' => 'running'],
                'message' => '',
            ], 200),
        ]);

        $client = app(SagaCoordinatorClient::class);
        $detail = $client->detail(12_001);

        $this->assertSame(['state' => 'running'], $detail);
        Http::assertSent(function ($request) {
            if ($request->method() !== 'GET') {
                return false;
            }
            $u = parse_url((string) $request->url());

            return ($u['host'] ?? '') === 'gw.test'
                && ($u['path'] ?? '') === '/api/saga/instances/detail'
                && (($u['query'] ?? '') === 'idem_key=12001');
        });
    }

    public function test_start_maps_http_500_to_runtime_exception(): void
    {
        Http::fake([
            'http://gw.test/api/saga/instances/start' => Http::response('oops', 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Saga start HTTP 500');

        app(SagaCoordinatorClient::class)->start(['access_key' => 'k', 'flow_id' => 1]);
    }
}
