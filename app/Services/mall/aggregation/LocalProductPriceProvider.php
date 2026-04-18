<?php

declare(strict_types=1);

namespace App\Services\mall\aggregation;

use App\Contracts\UserBusinessServiceContract;
use App\Services\mall\ProductPriceService;

/**
 * Read-side ProviderContract: resolves prices via local DB (function-call underlying layer).
 * Enable in config/mall_agg.php business_services when you need this slice in a shared aggregation executor.
 */
final readonly class LocalProductPriceProvider implements UserBusinessServiceContract
{
    public function __construct(private ProductPriceService $prices)
    {
    }

    public function key(): string
    {
        return 'product_price';
    }

    public function supports(array $context): bool
    {
        return isset($context['mall_product_ids']) && is_array($context['mall_product_ids']);
    }

    /**
     * @param array<string, mixed> $subject
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function fetch(array $subject, array $context): array
    {
        $ids = $context['mall_product_ids'];
        if (!is_array($ids)) {
            return ['prices' => []];
        }
        $intIds = array_map(static fn(mixed $v): int => (int)$v, $ids);

        return [
            'prices' => $this->prices->getPriceByProductIds($intIds),
        ];
    }
}
