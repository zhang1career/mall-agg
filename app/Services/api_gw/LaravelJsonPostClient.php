<?php

declare(strict_types=1);

namespace App\Services\api_gw;

use Illuminate\Support\Facades\Http;
use Paganini\Foundation\Http\JsonPostClientInterface;
use RuntimeException;

/**
 * {@see JsonPostClientInterface} backed by Laravel HTTP client (respects {@code Http::fake} in tests).
 */
final readonly class LaravelJsonPostClient implements JsonPostClientInterface
{
    private string $baseUrl;

    public function __construct(
        string $baseUrl,
        private int $timeoutSeconds = 10,
    ) {
        $t = trim($baseUrl);
        if ($t === '') {
            throw new \InvalidArgumentException('baseUrl must be non-empty.');
        }
        $this->baseUrl = rtrim($t, '/');
    }

    public function postJson(string $path, array $body, array $headers = []): array
    {
        $url = $this->baseUrl.'/'.ltrim($path, '/');
        $response = Http::timeout(max(1, $this->timeoutSeconds))
            ->acceptJson()
            ->asJson()
            ->withHeaders($headers)
            ->post($url, $body);

        if (! $response->successful()) {
            throw new RuntimeException('HTTP POST '.$response->status().' for '.$url);
        }
        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Invalid JSON object response from '.$url);
        }

        /** @var array<string, mixed> $json */
        return $json;
    }
}
