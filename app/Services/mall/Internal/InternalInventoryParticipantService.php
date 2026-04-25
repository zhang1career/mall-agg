<?php

declare(strict_types=1);

namespace App\Services\mall\Internal;

use App\Contracts\InventoryOutboundContract;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Saga inventory step: remote reserve only (checkout saga).
 */
final class InternalInventoryParticipantService
{
    private const TRY_RESPONSE_CACHE = 'saga:inv:try_resp:';

    private const RESERVE_TO_IDEM_CACHE = 'saga:inv:reserve_to_idem:';

    private const CACHE_TTL_SECONDS = 86_400;

    public function __construct(
        private readonly InventoryOutboundContract $inventoryOutbound,
    ) {}

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lines
     * @return array{inventory_token: string, mode: string}
     */
    public function actionPhase(
        int $uid,
        array $lines,
        string $sagaStepIdemKey,
    ): array {
        if ($uid < 1) {
            throw new RuntimeException('Invalid uid.');
        }
        if ($lines === []) {
            throw new RuntimeException('lines must not be empty.');
        }
        if (trim($sagaStepIdemKey) === '') {
            throw new RuntimeException('saga_step_idem_key is required.');
        }

        $idem = trim($sagaStepIdemKey);
        $respKey = self::TRY_RESPONSE_CACHE.$idem;
        $cached = Cache::get($respKey);
        if (is_array($cached) && isset($cached['inventory_token'], $cached['mode'])) {
            /** @var array{inventory_token: string, mode: string} $cached */

            return $cached;
        }

        $merged = $this->mergeLines($lines);
        $reserveLines = [];
        foreach ($merged as $productId => $quantity) {
            $reserveLines[] = ['product_id' => (int) $productId, 'quantity' => (int) $quantity];
        }
        $reserved = $this->inventoryOutbound->reserve($uid, $reserveLines);
        $token = $reserved['reserve_id'];
        Cache::put(self::RESERVE_TO_IDEM_CACHE.$token, $idem, self::CACHE_TTL_SECONDS);
        $out = ['inventory_token' => $token, 'mode' => 'remote'];
        Cache::put($respKey, $out, self::CACHE_TTL_SECONDS);

        return $out;
    }

    public function confirmPhase(string $inventoryToken): void
    {
        $token = trim($inventoryToken);
        if ($token === '') {
            throw new RuntimeException('inventory_token is required.');
        }

        $reserveIdem = Cache::get(self::RESERVE_TO_IDEM_CACHE.$token);
        if (is_string($reserveIdem) && $reserveIdem !== '') {
            Cache::forget(self::TRY_RESPONSE_CACHE.$reserveIdem);
            Cache::forget(self::RESERVE_TO_IDEM_CACHE.$token);
        }
    }

    public function compensatePhase(string $inventoryToken): void
    {
        $token = trim($inventoryToken);
        if ($token === '') {
            throw new RuntimeException('inventory_token is required.');
        }

        $this->inventoryOutbound->release($token);
        $reserveIdem = Cache::get(self::RESERVE_TO_IDEM_CACHE.$token);
        Cache::forget(self::RESERVE_TO_IDEM_CACHE.$token);
        if (is_string($reserveIdem) && $reserveIdem !== '') {
            Cache::forget(self::TRY_RESPONSE_CACHE.$reserveIdem);
        }
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lines
     * @return array<int, int> product_id => quantity
     */
    private function mergeLines(array $lines): array
    {
        /** @var array<int, int> $merged */
        $merged = [];
        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);
            if ($productId < 1 || $quantity < 1) {
                throw new RuntimeException('Invalid order line.');
            }
            $merged[$productId] = ($merged[$productId] ?? 0) + $quantity;
        }

        return $merged;
    }
}
