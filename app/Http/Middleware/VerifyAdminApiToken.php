<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyAdminApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('mall_agg.admin.api_token', '');
        if ($expected === '') {
            abort(503, 'Admin API token is not configured.');
        }

        $auth = (string) $request->header('Authorization', '');
        $token = str_starts_with($auth, 'Bearer ') ? trim(substr($auth, 7)) : '';

        if ($token === '' || ! hash_equals($expected, $token)) {
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}
