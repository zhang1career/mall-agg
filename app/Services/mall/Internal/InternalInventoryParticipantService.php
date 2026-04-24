<?php

declare(strict_types=1);

namespace App\Services\mall\Internal;

use App\Contracts\InventoryOutboundContract;
use App\Services\mall\ProductInventoryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Saga-facing inventory: routes to remote reserve/release or local DB decrement/increment.
 */
final class InternalInventoryParticipantService
{
    private const TRY_RESPONSE_CACHE = 'saga:inv:try_resp:';

    private const LOCAL_LINES_CACHE = 'saga:inv:local_lines:';

    private const RESERVE_TO_IDEM_CACHE = 'saga:inv:reserve_to_idem:';

    private const LOCAL_HOLD_PREFIX = 'localhold:';

    private const CACHE_TTL_SECONDS = 86_400;

    public static function localHoldPrefix(): string
    {
        return self::LOCAL_HOLD_PREFIX;
    }

    public function __construct(
        private readonly ProductInventoryService $inventory,
        private readonly InventoryOutboundContract $inventoryOutbound,
    ) {}

    /**
     * Saga action: reserve or adopt an existing remote reserve (when `reuse_inventory_token` is set from checkout pre-reserve).
     *
     * @param  list<array{product_id: int, quantity: int}>  $lines
     * @return array{inventory_token: string, mode: string}
     */
    public function actionPhase(
        int $uid,
        array $lines,
        string $sagaStepIdemKey,
        ?string $reuseInventoryToken = null,
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

        $reuse = $reuseInventoryToken !== null ? trim($reuseInventoryToken) : '';
        if ($reuse !== '' && (bool) config('mall_agg.checkout.use_saga_coordinators', false)) {
            Cache::put(self::RESERVE_TO_IDEM_CACHE.$reuse, $idem, self::CACHE_TTL_SECONDS);
            $out = ['inventory_token' => $reuse, 'mode' => 'remote'];
            Cache::put($respKey, $out, self::CACHE_TTL_SECONDS);

            return $out;
        }

        $merged = $this->mergeLines($lines);

        if ((bool) config('mall_agg.checkout.use_saga_coordinators', false)) {
            $reserved = $this->inventoryOutbound->reserve($uid, $lines);
            $token = $reserved['reserve_id'];
            Cache::put(self::RESERVE_TO_IDEM_CACHE.$token, $idem, self::CACHE_TTL_SECONDS);
            $out = ['inventory_token' => $token, 'mode' => 'remote'];
        } else {
            DB::transaction(function () use ($merged): void {
                foreach ($merged as $productId => $quantity) {
                    $this->inventory->lockAndDecrement($productId, $quantity);
                }
            });
            $token = self::LOCAL_HOLD_PREFIX.$idem;
            Cache::put(self::LOCAL_LINES_CACHE.$idem, $merged, self::CACHE_TTL_SECONDS);
            $out = ['inventory_token' => $token, 'mode' => 'local'];
        }

        Cache::put($respKey, $out, self::CACHE_TTL_SECONDS);

        return $out;
    }

    public function confirmPhase(string $inventoryToken): void
    {
        $token = trim($inventoryToken);
        if ($token === '') {
            throw new RuntimeException('inventory_token is required.');
        }

        if (str_starts_with($token, self::LOCAL_HOLD_PREFIX)) {
            $idem = substr($token, strlen(self::LOCAL_HOLD_PREFIX));
            Cache::forget(self::LOCAL_LINES_CACHE.$idem);
            Cache::forget(self::TRY_RESPONSE_CACHE.$idem);

            return;
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

        if (str_starts_with($token, self::LOCAL_HOLD_PREFIX)) {
            $idem = substr($token, strlen(self::LOCAL_HOLD_PREFIX));
            /** @var array<int, int>|null $merged */
            $merged = Cache::get(self::LOCAL_LINES_CACHE.$idem);
            if (! is_array($merged) || $merged === []) {
                Cache::forget(self::TRY_RESPONSE_CACHE.$idem);

                return;
            }

            DB::transaction(function () use ($merged): void {
                foreach ($merged as $productId => $quantity) {
                    $this->inventory->lockAndIncrement((int) $productId, (int) $quantity);
                }
            });

            Cache::forget(self::LOCAL_LINES_CACHE.$idem);
            Cache::forget(self::TRY_RESPONSE_CACHE.$idem);

            return;
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
