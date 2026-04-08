<?php

declare(strict_types=1);

namespace App\Services\Mall;

use App\Services\Mall\ServFd\CmsProductClient;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;

final class MallCatalogService
{
    public function __construct(
        private readonly CmsProductClient $cms,
        private readonly ProductPriceService $prices,
        private readonly ProductInventoryService $inventory,
    ) {}

    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function listProductsWithPrices(int $page, int $perPage): array
    {
        $pack = $this->cms->paginate($page, $perPage);
        $items = $pack['items'];
        $ids = [];
        foreach ($items as $row) {
            if (isset($row['id'])) {
                $ids[] = (int) $row['id'];
            }
        }
        $priceMap = $this->prices->getPriceByProductIds($ids);

        $outItems = [];
        foreach ($items as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            $row['price'] = $priceMap[$id] ?? null;
            $row['currency'] = 'MINOR';
            $outItems[] = $row;
        }

        return [
            'items' => $outItems,
            'pagination' => $pack['pagination'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getProductWithPriceAndStock(int $id): array
    {
        try {
            $row = $this->cms->find($id);
        } catch (DownstreamServiceException $e) {
            throw $e;
        }

        $priceMap = $this->prices->getPriceByProductIds([$id]);
        $qtyMap = $this->inventory->getQuantityByProductIds([$id]);

        $row['price'] = $priceMap[$id] ?? null;
        $row['currency'] = 'MINOR';
        $row['stock_quantity'] = $qtyMap[$id] ?? 0;

        return $row;
    }
}
