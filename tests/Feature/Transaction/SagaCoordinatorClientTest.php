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
            'http://gw.test/api/saga/instances' => Http::response([
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
            return $request->url() === 'http://gw.test/api/saga/instances'
                && $request->method() === 'POST'
                && ($request->data()['flow_id'] ?? null) === 7;
        });
    }

    public function test_get_instance_uses_path_param_per_openapi(): void
    {
        Http::fake([
            'http://gw.test/api/saga/instances/42' => Http::response([
                'errorCode' => 0,
                'data' => [
                    'saga_instance_id' => '42',
                    'idem_key' => 12_001,
                    'context' => ['k' => 1],
                    'step_runs' => [],
                ],
                'message' => '',
            ], 200),
        ]);

        $client = app(SagaCoordinatorClient::class);
        $detail = $client->getInstance('42');

        $this->assertSame(['k' => 1], $detail['context']);
        Http::assertSent(function ($request) {
            if ($request->method() !== 'GET') {
                return false;
            }

            return (string) $request->url() === 'http://gw.test/api/saga/instances/42';
        });
    }

    public function test_start_maps_http_500_to_runtime_exception(): void
    {
        Http::fake([
            'http://gw.test/api/saga/instances' => Http::response('oops', 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Saga start HTTP 500');

        app(SagaCoordinatorClient::class)->start(['access_key' => 'k', 'flow_id' => 1]);
    }
}
