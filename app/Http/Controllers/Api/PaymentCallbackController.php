<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Placeholder until payment gateway contract and routing are fixed.
 */
final class PaymentCallbackController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(
            ApiResponse::error(50101, 'Payment callback is not configured.'),
            501
        );
    }
}
