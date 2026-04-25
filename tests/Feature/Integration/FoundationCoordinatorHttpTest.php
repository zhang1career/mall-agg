<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Services\Transaction\CoordinatorEnvelope;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * 可选：本机 service_foundation 网关上的 Saga/TCC 健康检查（与 {@see CoordinatorEnvelope} 对齐）。
 *
 * 未启动网关时跳过（不拖垮 CI）。本地联调可设 FOUNDATION_COORDINATOR_BASE_URL。
 *
 * @group foundation-coordinator
 * @group integration
 */
final class FoundationCoordinatorHttpTest extends TestCase
{
    private function coordinatorBaseUrl(): string
    {
        return rtrim((string) env(
            'FOUNDATION_COORDINATOR_BASE_URL',
            'http://127.0.0.1:18041'
        ), '/');
    }

    public function test_tcc_health_returns_ok_envelope_when_gateway_reachable(): void
    {
        $url = $this->coordinatorBaseUrl().'/api/tcc/health';

        try {
            $response = Http::timeout(2)->acceptJson()->get($url);
        } catch (\Throwable) {
            $this->markTestSkipped('TCC base not reachable: '.$this->coordinatorBaseUrl());

            return;
        }

        if (! $response->successful()) {
            $this->markTestSkipped('TCC health HTTP '.$response->status());

            return;
        }

        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertSame(0, (int) ($json['errorCode'] ?? -1), 'TCC health must use errorCode=0 envelope.');
        $this->assertIsArray($json['data'] ?? null);
        $this->assertSame('tcc', $json['data']['service'] ?? null);
    }

    public function test_saga_health_returns_ok_envelope_when_gateway_reachable(): void
    {
        $url = $this->coordinatorBaseUrl().'/api/saga/health';

        try {
            $response = Http::timeout(2)->acceptJson()->get($url);
        } catch (\Throwable) {
            $this->markTestSkipped('Saga base not reachable: '.$this->coordinatorBaseUrl());

            return;
        }

        if (! $response->successful()) {
            $this->markTestSkipped('Saga health HTTP '.$response->status());

            return;
        }

        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertSame(0, (int) ($json['errorCode'] ?? -1), 'Saga health must use errorCode=0 envelope.');
        $this->assertIsArray($json['data'] ?? null);
        $this->assertSame('saga', $json['data']['service'] ?? null);
    }
}
