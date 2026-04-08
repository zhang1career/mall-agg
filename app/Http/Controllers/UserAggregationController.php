<?php

namespace App\Http\Controllers;

use App\Components\ApiResponse;
use App\Exceptions\FoundationAuthRequiredException;
use App\Services\User\UserAggregationExecutor;
use App\Services\User\UserDegradePolicy;
use App\Services\User\UserFoundationGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Paganini\Capability\ProviderRegistry;

class UserAggregationController extends Controller
{
    public function me(
        Request $request,
        UserFoundationGateway $foundationGateway,
        ProviderRegistry $registry,
        UserAggregationExecutor $executor,
        UserDegradePolicy $degradePolicy
    ): JsonResponse {
        $token = $request->bearerToken();
        if ($token === null || trim($token) === '') {
            return response()->json(ApiResponse::error(
                (int) config('mall_agg.foundation.unauthorized_code', 40101),
                'Authorization required. Call POST /api/user/login first, store access_token on the client, then call this endpoint with header: Authorization: Bearer <access_token> (single space after Bearer).'
            ), 401);
        }

        try {
            $baseUser = $foundationGateway->fetchCurrentUser($request);
        } catch (FoundationAuthRequiredException $e) {
            return response()->json(ApiResponse::error(
                (int) config('mall_agg.foundation.unauthorized_code', 40101),
                $e->getMessage()
            ), 401);
        }

        $this->logHandledApiRequest($request, [
            'handler' => 'me',
            'foundation_user_id' => $baseUser['id'] ?? $baseUser['user_id'] ?? null,
        ]);

        $context = [
            'path' => $request->path(),
            'query' => $request->query(),
            'headers' => $request->headers->all(),
            'trace_id' => $request->header('X-Trace-Id'),
            'bearer_token' => $request->bearerToken(),
        ];

        $result = $executor->execute(
            $registry->matched($context),
            $baseUser,
            $context,
            $degradePolicy
        );

        $hasDegraded = $result->hasDegraded();
        $responseCode = $hasDegraded
            ? (int) config('mall_agg.degrade.partial_failure_code', 20601)
            : 0;
        $responseMsg = $hasDegraded
            ? (string) config('mall_agg.degrade.partial_failure_message', 'Partially failed, degraded by aggregator.')
            : '';

        return response()->json(ApiResponse::code([
            'user' => $baseUser,
            'biz' => $result->biz,
            'meta' => [
                'degraded' => $hasDegraded,
                'degraded_keys' => $result->degradedKeys,
                'keys_used' => $result->keysUsed,
                'degrade_strategy' => $degradePolicy->strategy(),
                'execution_mode' => (string) config('mall_agg.execution.mode', 'serial'),
            ],
        ], $responseCode, $responseMsg));
    }
}
