<?php

declare(strict_types=1);

namespace App\Http\Client;

use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Logs PSR-7 request/response for Laravel's global Http client middleware (debug only).
 */
final class OutboundHttpDebugMiddleware
{
    private const MAX_BODY_LEN = 8192;

    /** @var list<string> */
    private const REQUEST_REDACT_HEADERS = ['authorization', 'proxy-authorization', 'cookie'];

    /** @var list<string> */
    private const RESPONSE_REDACT_HEADERS = ['set-cookie', 'set-cookie2'];

    public static function logRequest(RequestInterface $request): RequestInterface
    {
        Log::debug('http_outbound', [
            'phase' => 'request',
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'headers' => self::redactHeaders($request->getHeaders(), self::REQUEST_REDACT_HEADERS),
            'body' => self::safeReadBody($request->getBody()),
        ]);

        return $request;
    }

    public static function logResponse(ResponseInterface $response): ResponseInterface
    {
        Log::debug('http_outbound', [
            'phase' => 'response',
            'status' => $response->getStatusCode(),
            'reason' => $response->getReasonPhrase(),
            'headers' => self::redactHeaders($response->getHeaders(), self::RESPONSE_REDACT_HEADERS),
            'body' => self::safeReadBody($response->getBody()),
        ]);

        return $response;
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     * @param  list<string>  $redactLowerNames
     * @return array<string, array<int, string>>
     */
    private static function redactHeaders(array $headers, array $redactLowerNames): array
    {
        $redact = array_fill_keys($redactLowerNames, true);
        $out = [];
        foreach ($headers as $name => $values) {
            $out[$name] = isset($redact[strtolower($name)]) ? ['REDACTED'] : $values;
        }

        return $out;
    }

    private static function safeReadBody(StreamInterface $stream): string
    {
        if (! $stream->isReadable()) {
            return '';
        }
        if (! $stream->isSeekable()) {
            return '[non-seekable body omitted]';
        }

        $pos = $stream->tell();
        try {
            $raw = $stream->getContents();
            $stream->seek($pos);
        } catch (Throwable) {
            return '[body read failed]';
        }

        $len = strlen($raw);
        if ($len > self::MAX_BODY_LEN) {
            return substr($raw, 0, self::MAX_BODY_LEN).'…[truncated]';
        }

        return $raw;
    }
}
