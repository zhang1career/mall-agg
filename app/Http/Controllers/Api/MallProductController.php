<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Mall\MallCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;

class MallProductController extends Controller
{
    public function __construct(
        private readonly MallCatalogService $catalog,
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
}
