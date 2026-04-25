<?php

declare(strict_types=1);

namespace App\Services\mall\serv_fd;

use App\Services\api_gw\ResolvedApiGatewayBaseUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;
use Paganini\Aggregation\Support\DownstreamPayload;
use RuntimeException;

/**
 * app_searchrec HTTP client (see service_foundation docs/api_searchrec.json).
 */
final readonly class SearchRecClient
{
    public function __construct(
        private string $baseUrl,
        private string $accessKey,
        private int    $timeoutSeconds)
    {
    }

    public static function fromConfig(): self
    {
        $base = app(ResolvedApiGatewayBaseUrl::class)->resolvePathSuffix('/api/searchrec');
        $key = (string)config('api_gw.searchrec.access_key', '');
        $timeout = (int)config('api_gw.searchrec.timeout_seconds', 5);

        return new self($base, $key, $timeout);
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->accessKey !== '';
    }

    /**
     * @param array{title: string, description?: string, thumbnail?: string, main_media?: string, ext_media?: string} $productFields
     * @throws ConnectionException
     */
    public function upsertProduct(int $productId, array $productFields): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        $title = $productFields['title'] ?? '';
        if (!is_string($title)) {
            throw new RuntimeException('Product title must be a string.');
        }
        $description = $productFields['description'] ?? '';
        if (!is_string($description)) {
            throw new RuntimeException('Product description must be a string.');
        }

        $payload = [
            'access_key' => $this->accessKey,
            'documents' => [
                [
                    'id' => (string)$productId,
                    'title' => $title,
                    'content' => $description,
                    'tags' => [],
                    'score_boost' => 1.0,
                    'popularity_score' => 0.0,
                    'freshness_score' => 0.0,
                ],
            ],
        ];

        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl . '/index', $payload);

        $this->unwrapEnvelope($response, 'searchrec index');
    }

    /**
     * @param list<string> $preferredTags
     * @return array<string, mixed>
     * @throws ConnectionException
     */
    public function search(string $query, int $topK = 10, array $preferredTags = []): array
    {
        if (!$this->isConfigured()) {
            throw new DownstreamServiceException('SearchRec is not configured.');
        }

        $topK = min(100, max(1, $topK));

        $payload = [
            'access_key' => $this->accessKey,
            'query' => $query,
            'top_k' => $topK,
            'preferred_tags' => $preferredTags,
        ];

        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl . '/search', $payload);

        return $this->unwrapEnvelope($response, 'searchrec search');
    }

    /**
     * @return array<string, mixed>
     */
    private function unwrapEnvelope(Response $response, string $label): array
    {
        if (!$response->successful()) {
            throw new DownstreamServiceException(
                sprintf('%s failed with HTTP %d.', $label, $response->status())
            );
        }

        $json = $response->json();
        if (!is_array($json)) {
            throw new DownstreamServiceException('Invalid JSON from ' . $label);
        }

        if ((int)($json['errorCode'] ?? -1) !== 0) {
            $message = (string)($json['message'] ?? 'downstream error');

            throw new DownstreamServiceException($label . ' error: ' . $message);
        }

        return DownstreamPayload::extractData($json, $label);
    }
}
