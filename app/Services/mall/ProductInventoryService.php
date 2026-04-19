<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Models\ProductInventory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ProductInventoryService
{
    /**
     * @param  list<int>  $productIds
     * @return array<int, int> pid => quantity
     */
    public function getQuantityByProductIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = ProductInventory::query()
            ->whereIn('pid', $productIds)
            ->get(['pid', 'quantity']);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->pid] = (int) $row->quantity;
        }

        return $map;
    }

    public function upsertQuantity(int $productId, int $quantity): ProductInventory
    {
        $row = ProductInventory::query()->where('pid', $productId)->first();
        if ($row === null) {
            $row = new ProductInventory(['pid' => $productId]);
        }
        $row->quantity = $quantity;
        $row->save();

        return $row;
    }

    public function deleteForProduct(int $productId): void
    {
        ProductInventory::query()->where('pid', $productId)->delete();
    }

    /**
     * Lock row and decrement quantity; creates inventory row at 0 if missing.
     *
     * @throws RuntimeException when insufficient stock
     */
    public function lockAndDecrement(int $productId, int $decrement): void
    {
        if ($decrement < 1) {
            throw new RuntimeException('Decrement must be at least 1.');
        }

        DB::transaction(function () use ($productId, $decrement): void {
            $row = ProductInventory::query()
                ->where('pid', $productId)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw new RuntimeException('No inventory row for product '.$productId);
            }

            if ($row->quantity < $decrement) {
                throw new RuntimeException('Insufficient stock for product '.$productId);
            }

            $row->quantity -= $decrement;
            $row->save();
        });
    }

    public function lockAndIncrement(int $productId, int $increment): void
    {
        if ($increment < 1) {
            throw new RuntimeException('Increment must be at least 1.');
        }

        DB::transaction(function () use ($productId, $increment): void {
            $row = ProductInventory::query()
                ->where('pid', $productId)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw new RuntimeException('No inventory row for product '.$productId);
            }

            $row->quantity += $increment;
            $row->save();
        });
    }

    public function lockForUpdateOrFail(int $productId): ProductInventory
    {
        $row = ProductInventory::query()
            ->where('pid', $productId)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            throw (new ModelNotFoundException)->setModel(ProductInventory::class, [$productId]);
        }

        return $row;
    }
}
