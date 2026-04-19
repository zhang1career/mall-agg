<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Services\mall\serv_fd\CmsProductClient;
use Illuminate\Http\Client\ConnectionException;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;

final readonly class MallCatalogService
{
    public function __construct(
        private CmsProductClient        $cms,
        private ProductPriceService     $prices,
        private ProductInventoryService $inventory)
    {
    }

    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, mixed>}
     * @throws ConnectionException
     */
    public function listProductsWithPrices(int $page, int $perPage): array
    {
        $pack = $this->cms->paginate($page, $perPage);
        $items = $pack['items'];
        $ids = [];
        foreach ($items as $row) {
            if (isset($row['id'])) {
                $ids[] = (int)$row['id'];
            }
        }
        $priceMap = $this->prices->getPriceByProductIds($ids);

        $outItems = [];
        foreach ($items as $row) {
            $id = isset($row['id']) ? (int)$row['id'] : 0;
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
     * @throws ConnectionException
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
