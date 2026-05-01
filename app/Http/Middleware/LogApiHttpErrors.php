<?php

namespace App\Http\Middleware;

use App\Components\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Paganini\Constants\ResponseConstant;
use Symfony\Component\HttpFoundation\Response;

/**
 * 下游代理类接口「成功」时也可能返回 4xx/5xx，且不会抛异常，withExceptions 不会执行。
 * 在此统一记录非 2xx，并可选择将 5xx JSON 替换为对前端可控的文案。
 */
class LogApiHttpErrors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('api/*')) {
            return $response;
        }

        $status = $response->getStatusCode();
        if ($status < 400) {
            return $response;
        }

        $reqId = $request->header('X-Request-Id') ?: bin2hex(random_bytes(8));

        if (config('mall_agg.api.log_http_errors', true)) {
            $level = $status >= 500 ? 'error' : 'warning';
            Log::log($level, 'api http error', [
                'path' => $request->path(),
                'method' => $request->method(),
                'status' => $status,
                '_req_id' => $reqId,
                'response_preview' => $this->contentPreview($response),
            ]);
        }

        if (
            $status >= 500
            && config('mall_agg.api.normalize_5xx_json_body', false)
        ) {
            $message = (string) config('mall_agg.api.normalize_5xx_message', '服务器内部错误');

            return response()->json(
                ApiResponse::error(ResponseConstant::RET_UNKNOWN, $message, $reqId),
                $status
            );
        }

        return $response;
    }

    private function contentPreview(Response $response): string
    {
        $content = $response->getContent();
        if (! is_string($content)) {
            return '';
        }
        if (strlen($content) > 2048) {
            return substr($content, 0, 2048).'…';
        }

        return $content;
    }
}
