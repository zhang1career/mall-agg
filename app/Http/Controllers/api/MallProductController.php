<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Exceptions\ConfigurationMissingException;
use App\Http\Controllers\Controller;
use App\Services\mall\MallCatalogService;
use App\Services\mall\serv_fd\SearchRecClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MallProductController extends Controller
{
    public function __construct(
        private readonly MallCatalogService $catalog,
        private readonly SearchRecClient $searchRec,
    ) {}

    /**
     * @throws ConnectionException
     */
    public function index(Request $request): JsonResponse
    {
        $requestedPage = (int) $request->query('page', 1);
        $page = max(1, $requestedPage);

        $requestedPerPage = (int) $request->query('per_page', 15);
        $perPage = min(50, max(1, $requestedPerPage));

        $pack = $this->catalog->listProductsWithPrices($page, $perPage);

        $this->logHandledApiRequest($request, ['handler' => 'mall.products.index']);

        return response()->json(ApiResponse::ok($pack));
    }

    /**
     * @throws ConnectionException
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $row = $this->catalog->getProductWithPriceAndStock($id);

        $this->logHandledApiRequest($request, ['handler' => 'mall.products.show', 'product_id' => $id]);

        return response()->json(ApiResponse::ok($row));
    }

    /**
     * @throws ConfigurationMissingException
     * @throws ConnectionException
     */
    public function search(Request $request): JsonResponse
    {
        if (! $this->searchRec->isConfigured()) {
            throw new ConfigurationMissingException('Search service is not configured.');
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

        $data = $this->searchRec->search($query, $topK, $tags);

        $this->logHandledApiRequest($request, ['handler' => 'mall.products.search']);

        return response()->json(ApiResponse::ok($data));
    }
}
