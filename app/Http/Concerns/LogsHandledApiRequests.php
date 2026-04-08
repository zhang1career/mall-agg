<?php

namespace App\Http\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

trait LogsHandledApiRequests
{
    /**
     * Log after middleware (rate limit, global auth) and any in-controller checks you perform before calling this.
     *
     * @param  array<string, mixed>  $extras
     */
    protected function logHandledApiRequest(Request $request, array $extras = []): void
    {
        Log::info('[api] handled request', array_merge([
            'method' => $request->method(),
            'path' => $request->path(),
            'route_uri' => $request->route()?->uri(),
            'ip' => $request->ip(),
            'x_forwarded_for' => $request->header('X-Forwarded-For'),
            'x_trace_id' => $request->header('X-Trace-Id'),
            'user_agent' => $request->userAgent(),
            'query' => $request->query(),
            'input' => $this->sanitizeRequestInput($request->all()),
        ], $extras));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitizeRequestInput(array $data): array
    {
        $redact = [
            'password',
            'current_password',
            'new_password',
            'refresh_token',
            'access_token',
            'token',
            'authorization',
            'code',
            'otp',
            'verification_code',
        ];

        foreach ($data as $key => $value) {
            if ($value instanceof UploadedFile) {
                $data[$key] = [
                    '_file' => true,
                    'client_original_name' => $value->getClientOriginalName(),
                    'size' => $value->getSize(),
                    'client_mime_type' => $value->getClientMimeType(),
                ];

                continue;
            }

            if (is_string($key) && in_array(strtolower($key), $redact, true)) {
                $data[$key] = '***';
            }
        }

        return $data;
    }
}
