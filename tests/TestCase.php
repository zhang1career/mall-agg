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
     * @return array<string, mixed>
     */
    protected function fakeSagaCoordinatorStartHttp(int $idemKey = 88_001, int $sagaInstanceId = 1): array
    {
        return [
            'http://foundation.local/api/saga/instances' => Http::response([
                'errorCode' => 0,
                'data' => [
                    'saga_instance_id' => $sagaInstanceId,
                    'idem_key' => $idemKey,
                ],
                'message' => '',
            ], 200),
        ];
    }
}
