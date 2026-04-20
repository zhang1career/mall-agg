<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyInternalParticipantToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('mall_agg.internal.participant_token', '');
        if ($expected === '') {
            abort(503, 'Internal participant token is not configured.');
        }
        $token = (string) $request->header('X-Internal-Token', '');
        if (! hash_equals($expected, $token)) {
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}
