<?php

declare(strict_types=1);

namespace App\Services\Outbound;

use App\Contracts\InventoryOutboundContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Calls external inventory HTTP API when base URL is set; otherwise in-memory stub for tests.
 */
final class StubInventoryOutboundClient implements InventoryOutboundContract
{
    public function reserve(int $uid, array $lines): array
    {
        $base = (string) config('mall_agg.outbound.inventory.base_url', '');
        if ($base === '') {
            return ['reserve_id' => 'stub:'.Str::uuid()->toString()];
        }

        $timeout = (int) config('mall_agg.outbound.inventory.timeout_seconds', 5);
        $url = rtrim($base, '/').'/reserve';
        $response = Http::timeout($timeout)->acceptJson()->asJson()->post($url, [
            'uid' => $uid,
            'lines' => $lines,
        ]);
        if (! $response->successful()) {
            throw new RuntimeException('Inventory reserve HTTP '.$response->status());
        }
        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Inventory reserve: invalid JSON.');
        }
        $reserveId = $json['reserve_id'] ?? null;
        if (! is_string($reserveId) || $reserveId === '') {
            throw new RuntimeException('Inventory reserve: missing reserve_id.');
        }

        return ['reserve_id' => $reserveId];
    }

    public function release(string $reserveId): void
    {
        $base = (string) config('mall_agg.outbound.inventory.base_url', '');
        if ($base === '') {
            return;
        }

        $timeout = (int) config('mall_agg.outbound.inventory.timeout_seconds', 5);
        $url = rtrim($base, '/').'/release';
        $response = Http::timeout($timeout)->acceptJson()->asJson()->post($url, [
            'reserve_id' => $reserveId,
        ]);
        if (! $response->successful()) {
            throw new RuntimeException('Inventory release HTTP '.$response->status());
        }
    }
}
