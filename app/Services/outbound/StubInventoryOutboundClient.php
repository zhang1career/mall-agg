<?php

declare(strict_types=1);

namespace App\Services\outbound;

use App\Contracts\InventoryOutboundContract;
use Illuminate\Support\Str;

/**
 * In-process stand-in until an external inventory API is implemented and bound.
 */
final class StubInventoryOutboundClient implements InventoryOutboundContract
{
    public function reserve(int $uid, array $lines): array
    {
        return ['reserve_id' => 'stub:'.Str::uuid()->toString()];
    }

    public function release(string $reserveId): void
    {
        // No external call until inventory outbound is integrated.
    }
}
