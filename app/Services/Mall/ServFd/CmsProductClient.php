<?php

declare(strict_types=1);

namespace App\Services\Mall\ServFd;

use App\Services\User\ResolvedFoundationBaseUrl;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;
use Paganini\Aggregation\Support\DownstreamPayload;
use RuntimeException;

/**
 * CMS content API client: {@link https://github.com/...} api_cms.json with content_route = product.
 */
final class CmsProductClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $contentRoute,
        private readonly int $timeoutSeconds,
    ) {}

    public static function fromConfig(): self
    {
        $base = app(ResolvedFoundationBaseUrl::class)->resolve();
        $route = (string) config('mall_agg.cms.content_route', 'product');
        $timeout = (int) config('mall_agg.serv_fd.timeout_seconds', 3);
        if ($base === '') {
            throw new RuntimeException('Missing API gateway base URL (API_GATEWAY_BASE_URL).');
        }

        return new self($base, $route, $timeout);
    }

    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->get($this->listUrl(), [
                'page' => $page,
                'per_page' => $perPage,
            ]);

        if ($response->status() === 404) {
            throw new DownstreamServiceException('CMS content route not found: '.$this->contentRoute);
        }

        if (! $response->successful()) {
            throw new DownstreamServiceException(
                sprintf('CMS list failed with HTTP %d.', $response->status())
            );
        }

        $data = DownstreamPayload::extractData($response->json(), 'cms product list');
        if (! isset($data['items'], $data['pagination']) || ! is_array($data['items']) || ! is_array($data['pagination'])) {
            throw new DownstreamServiceException('Invalid CMS list payload: missing items or pagination.');
        }

        return [
            'items' => $data['items'],
            'pagination' => $data['pagination'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function find(int $id): array
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->get($this->itemUrl($id));

        if ($response->status() === 404) {
            throw new DownstreamServiceException('CMS product not found: '.$id);
        }

        if (! $response->successful()) {
            throw new DownstreamServiceException(
                sprintf('CMS detail failed with HTTP %d.', $response->status())
            );
        }

        return DownstreamPayload::extractData($response->json(), 'cms product detail');
    }

    /**
     * @param  array{title: string, description?: string, thumbnail?: string, main_media?: string, ext_media?: string}  $fields
     * @return array<string, mixed>
     */
    public function create(array $fields): array
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson()
            ->post($this->listUrl(), $this->filterProductFields($fields));

        return $this->unwrapWriteResponse($response, 'cms product create', [200, 201]);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function update(int $id, array $fields): array
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson()
            ->put($this->itemUrl($id), $this->filterProductFields($fields));

        return $this->unwrapWriteResponse($response, 'cms product update', [200]);
    }

    public function delete(int $id): void
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->delete($this->itemUrl($id));

        if ($response->status() === 404) {
            throw new DownstreamServiceException('CMS product not found: '.$id);
        }

        if (! $response->successful()) {
            throw new DownstreamServiceException(
                sprintf('CMS delete failed with HTTP %d.', $response->status())
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new DownstreamServiceException('Invalid CMS delete payload.');
        }
        if ((int) ($json['errorCode'] ?? -1) !== 0) {
            $message = (string) ($json['message'] ?? 'downstream error');

            throw new DownstreamServiceException('CMS delete error: '.$message);
        }
    }

    private function listUrl(): string
    {
        return $this->baseUrl.'/api/cms/'.$this->contentRoute.'/';
    }

    private function itemUrl(int $id): string
    {
        return $this->baseUrl.'/api/cms/'.$this->contentRoute.'/'.$id.'/';
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, string>
     */
    private function filterProductFields(array $fields): array
    {
        $allowed = ['title', 'description', 'thumbnail', 'main_media', 'ext_media'];
        $out = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $fields)) {
                continue;
            }
            $value = $fields[$key];
            if ($value === null) {
                continue;
            }
            if (! is_string($value)) {
                throw new RuntimeException('Field '.$key.' must be a string.');
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @param  list<int>  $successStatuses
     * @return array<string, mixed>
     */
    private function unwrapWriteResponse(Response $response, string $label, array $successStatuses): array
    {
        $status = $response->status();
        if (! in_array($status, $successStatuses, true)) {
            throw new DownstreamServiceException(
                sprintf('%s failed with HTTP %d.', $label, $status)
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new DownstreamServiceException('Invalid JSON from '.$label);
        }

        if ((int) ($json['errorCode'] ?? -1) === 100) {
            $message = (string) ($json['message'] ?? 'validation failed');
            $data = $json['data'] ?? null;
            if (is_array($data)) {
                $first = '';
                foreach ($data as $k => $v) {
                    if (is_string($v)) {
                        $first = (string) $k.': '.$v;
                        break;
                    }
                }
                if ($first !== '') {
                    $message = $first;
                }
            }

            throw new DownstreamServiceException($label.' validation: '.$message);
        }

        return DownstreamPayload::extractData($json, $label);
    }
}
