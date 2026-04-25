<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\mall\MallCatalogService;
use App\Services\mall\serv_fd\SearchRecClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;

class MallProductController extends Controller
{
    public function __construct(
        private readonly MallCatalogService $catalog,
        private readonly SearchRecClient $searchRec,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        try {
            $pack = $this->catalog->listProductsWithPrices($page, $perPage);
        } catch (DownstreamServiceException $e) {
            return response()->json(ApiResponse::error(40401, $e->getMessage()), 404);
        }

        $this->logHandledApiRequest($request, ['handler' => 'mall.products.index']);

        return response()->json(ApiResponse::ok($pack));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $row = $this->catalog->getProductWithPriceAndStock($id);
        } catch (DownstreamServiceException $e) {
            return response()->json(ApiResponse::error(40401, $e->getMessage()), 404);
        }

        $this->logHandledApiRequest($request, ['handler' => 'mall.products.show', 'product_id' => $id]);

        return response()->json(ApiResponse::ok($row));
    }

    public function search(Request $request): JsonResponse
    {
        if (! $this->searchRec->isConfigured()) {
            return response()->json(
                ApiResponse::error(50301, 'Search service is not configured.'),
                503
            );
        }

        $validated = $request->validate([
            'query' => 'nullable|string|max:2000',
            'top_k' => 'nullable|integer|min:1|max:100',
            'preferred_tags' => 'nullable|array',
            'preferred_tags.*' => 'string|max:200',
        ]);

        $query = (string) ($validated['query'] ?? '');
        $topK = (int) ($validated['top_k'] ?? 10);
        /** @var list<string> $tags */
        $tags = array_values(array_filter(
            $validated['preferred_tags'] ?? [],
            static fn (string $t) => $t !== ''
        ));

        try {
            $data = $this->searchRec->search($query, $topK, $tags);
        } catch (DownstreamServiceException $e) {
            return response()->json(ApiResponse::error(50201, $e->getMessage()), 502);
        }

        $this->logHandledApiRequest($request, ['handler' => 'mall.products.search']);

        return response()->json(ApiResponse::ok($data));
    }
}
