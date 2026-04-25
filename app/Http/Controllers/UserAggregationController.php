<?php

namespace App\Http\Controllers;

use App\Components\ApiResponse;
use App\Exceptions\FoundationAuthRequiredException;
use App\Services\user\UserAggregationExecutor;
use App\Services\user\UserDegradePolicy;
use App\Services\user\UserFoundationGateway;
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
        $token = trim((string) $request->header('X-User-Access-Token', ''));
        if ($token === '') {
            return response()->json(ApiResponse::error(
                (int) config('mall_agg.foundation.unauthorized_code', 40101),
                'Authentication required. Call POST /api/user/login first, store access_token on the client, then send header: X-User-Access-Token: <access_token> (raw JWT, no Bearer prefix).'
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
            'user_access_token' => $token,
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
