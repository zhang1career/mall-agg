<?php

declare(strict_types=1);

namespace App\Services\Mall;

use App\Models\MallProductPrice;

final class ProductPriceService
{
    /**
     * @param  list<int>  $productIds
     * @return array<int, int> product_id => price_minor
     */
    public function getPriceMinorByProductIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = MallProductPrice::query()
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'price_minor']);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->product_id] = (int) $row->price_minor;
        }

        return $map;
    }

    public function upsertPrice(int $productId, int $priceMinor): MallProductPrice
    {
        $row = MallProductPrice::query()->where('product_id', $productId)->first();
        if ($row === null) {
            $row = new MallProductPrice(['product_id' => $productId]);
        }
        $row->price_minor = $priceMinor;
        $row->save();

        return $row;
    }

    public function deleteForProduct(int $productId): void
    {
        MallProductPrice::query()->where('product_id', $productId)->delete();
    }
}
