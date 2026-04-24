<?php

namespace Tests;

use App\Services\Transaction\SagaCoordinatorClient;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Fake {@see SagaCoordinatorClient} start envelope for tests using `api_gw.base_url` = foundation.local.
     *
     * @param  array<string, mixed>  $prepay
     * @return array<string, mixed>
     */
    protected function fakeSagaCoordinatorStartHttp(
        int $idemKey = 88_001,
        int $sagaInstanceId = 1,
        array $prepay = ['stub' => true, 'amount_minor' => 1],
        string $globalTxId = 'gtx-test',
        int $tccCoordIdemKey = 99_001,
        ?string $pointsBranchIdemKey = 'ord:1:testidem',
    ): array {
        $ctx = [
            'prepay' => $prepay,
            'global_tx_id' => $globalTxId,
            'idem_key' => $tccCoordIdemKey,
            'tcc_idem_key' => $pointsBranchIdemKey,
        ];

        return [
            'http://foundation.local/api/saga/instances' => Http::response([
                'errorCode' => 0,
                'data' => [
                    'saga_instance_id' => (string) $sagaInstanceId,
                    'idem_key' => $idemKey,
                    'flow_id' => 1,
                    'status' => 40,
                    'current_step_index' => 3,
                    'retry_count' => 0,
                    'last_error' => '',
                    'context' => $ctx,
                    'step_runs' => [],
                ],
                'message' => '',
            ], 200),
        ];
    }
}
