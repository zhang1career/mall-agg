<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Components\XxlResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Paganini\XxlJobExecutor\TokenAuthenticator;
use Symfony\Component\HttpFoundation\Response;

final class XxljobAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('XXL-JOB-ACCESS-TOKEN');
        $authenticator = new TokenAuthenticator((string) config('xxl.token'));

        if (! $authenticator->validate($token)) {
            Log::error('[xxljob] token validation failed');

            return response()->json(XxlResponse::fail('Token validation failed'));
        }

        return $next($request);
    }
}
