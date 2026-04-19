<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Models\ProductPrice;

final class ProductPriceService
{
    /**
     * @param list<int> $productIds
     * @return array<int, int> pid => price (minor units)
     */
    public function getPriceByProductIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = ProductPrice::query()
            ->whereIn('pid', $productIds)
            ->get(['pid', 'price']);

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row->pid] = (int)$row->price;
        }

        return $map;
    }

    public function upsertPrice(int $productId, int $price): ProductPrice
    {
        $row = ProductPrice::query()->where('pid', $productId)->first();
        if ($row === null) {
            $row = new ProductPrice(['pid' => $productId]);
        }
        $row->price = $price;
        $row->save();

        return $row;
    }

    public function deleteForProduct(int $productId): void
    {
        ProductPrice::query()->where('pid', $productId)->delete();
    }
}
