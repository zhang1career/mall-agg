<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Components\ApiResponse;
use App\Exceptions\FoundationAuthRequiredException;
use App\Http\Controllers\Controller;
use App\Services\mall\FoundationUser;
use App\Services\mall\MallPointsTccService;
use App\Services\user\UserFoundationGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MallPointsController extends Controller
{
    public function __construct(
        private readonly UserFoundationGateway $foundationGateway,
        private readonly MallPointsTccService $points,
    ) {}

    /**
     * Current user's available points balance (minor units).
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $user = $this->requireAuthenticatedUser($request);
        } catch (FoundationAuthRequiredException $e) {
            return $this->unauthorizedResponse($e);
        }

        $minor = $this->points->availableBalanceMinor(FoundationUser::id($user));

        $this->logHandledApiRequest($request, ['handler' => 'mall.points.show']);

        return response()->json(ApiResponse::ok([
            'balance_minor' => $minor,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function requireAuthenticatedUser(Request $request): array
    {
        $token = $request->bearerToken();
        if ($token === null || trim($token) === '') {
            throw new FoundationAuthRequiredException(
                'Authorization required. Send Authorization: Bearer <access_token>.'
            );
        }

        return $this->foundationGateway->fetchCurrentUser($request);
    }

    private function unauthorizedResponse(FoundationAuthRequiredException $e): JsonResponse
    {
        return response()->json(
            ApiResponse::error(
                (int) config('mall_agg.foundation.unauthorized_code', 40101),
                $e->getMessage()
            ),
            401
        );
    }
}
