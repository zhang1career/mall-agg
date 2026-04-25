<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\MallDictionaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MallDictController extends Controller
{
    public function __invoke(Request $request, MallDictionaryService $dictionary): JsonResponse
    {
        $reqId = $request->header('X-Request-Id') ?: '';
        $codesRaw = $request->query('codes');
        if (!is_string($codesRaw) || trim($codesRaw) === '') {
            return response()->json(ApiResponse::error(101, 'codes is required', $reqId));
        }

        $codes = array_values(array_filter(array_map(trim(...), explode(',', $codesRaw))));
        if ($codes === []) {
            return response()->json(ApiResponse::error(101, 'codes is required', $reqId));
        }

        $data = $dictionary->resolve($codes);

        return response()->json(ApiResponse::ok($data, '', $reqId));
    }
}
