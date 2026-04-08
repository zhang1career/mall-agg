<?php

declare(strict_types=1);

namespace App\Services\Mall\Aggregation;

use App\Contracts\UserBusinessServiceContract;
use App\Services\Mall\ProductInventoryService;

/**
 * Read-side ProviderContract: resolves stock quantities via local DB.
 */
final class LocalProductInventoryProvider implements UserBusinessServiceContract
{
    public function __construct(
        private readonly ProductInventoryService $inventory,
    ) {}

    public function key(): string
    {
        return 'mall_product_inventory';
    }

    public function supports(array $context): bool
    {
        return isset($context['mall_product_ids']) && is_array($context['mall_product_ids']);
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function fetch(array $subject, array $context): array
    {
        $ids = $context['mall_product_ids'];
        if (! is_array($ids)) {
            return ['quantities' => []];
        }
        $intIds = array_map(static fn (mixed $v): int => (int) $v, $ids);

        return [
            'quantities' => $this->inventory->getQuantityByProductIds($intIds),
        ];
    }
}
