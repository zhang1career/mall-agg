<?php

declare(strict_types=1);

namespace App\Contracts;

interface InventoryOutboundContract
{
    /**
     * Reserve stock for order lines; returns opaque reserve id for release.
     *
     * @param  list<array{product_id: int, quantity: int}>  $lines
     * @return array{reserve_id: string}
     */
    public function reserve(int $uid, array $lines): array;

    public function release(string $reserveId): void;
}
