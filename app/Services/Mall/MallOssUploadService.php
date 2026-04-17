<?php

declare(strict_types=1);

namespace App\Services\Mall;

use App\Services\User\ResolvedFoundationBaseUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Uploads files to the foundation OSS API (HTTP PUT), same mechanism as Django
 * app_user.services.avatar_storage_service (HTTP PUT to /api/oss/{bucket}/{key}).
 */
final class MallOssUploadService
{
    public function __construct(
        private readonly ResolvedFoundationBaseUrl $resolvedFoundationBaseUrl,
    ) {}

    /**
     * @return non-empty-string Object key stored in CMS (e.g. mall/products/{uuid}.jpg)
     */
    public function uploadProductFile(UploadedFile $uploadedFile): string
    {
        $prefix = trim((string) config('mall_upload.prefix'), '/');
        if ($prefix === '') {
            $prefix = 'mall/products';
        }

        $extension = $uploadedFile->getClientOriginalExtension() ?: $uploadedFile->extension() ?: 'bin';
        $pathId = (string) Str::uuid();
        $objectKey = $prefix.'/'.$pathId.'.'.$extension;

        $base = $this->resolvedFoundationBaseUrl->resolveRaw(trim((string) config('mall_upload.oss_base_url'), '/'));
        $bucket = trim((string) config('mall_upload.oss_bucket'), '/');

        if ($base === '' || $bucket === '') {
            throw new RuntimeException(
                'OSS upload is not configured: set API_GATEWAY_BASE_URL or SERV_FD_BASE_URL (or MALL_OSS_UPLOAD_BASE_URL) and MALL_OSS_BUCKET.'
            );
        }

        $encodedKey = $this->encodeObjectKeyForUrl($objectKey);
        $uploadUrl = $base.'/'.$bucket.'/'.$encodedKey;

        $mime = $uploadedFile->getMimeType() ?: 'application/octet-stream';
        $path = $uploadedFile->getRealPath();
        if ($path === false) {
            throw new RuntimeException('Temporary upload path unavailable.');
        }

        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Failed to read uploaded file.');
        }

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'Accept' => '*/*',
                ])
                ->withBody($stream, $mime)
                ->put($uploadUrl);
        } catch (\Throwable $e) {
            throw new RuntimeException('OSS upload request failed: '.$e->getMessage(), 0, $e);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($response->status() !== 200) {
            $preview = mb_substr($response->body(), 0, 500);

            throw new RuntimeException(
                sprintf('OSS upload rejected: HTTP %d. %s', $response->status(), $preview)
            );
        }

        return $objectKey;
    }

    /**
     * Encode object key path segments like Python's urllib.parse.quote(..., safe='/').
     */
    private function encodeObjectKeyForUrl(string $objectKey): string
    {
        $segments = explode('/', $objectKey);

        return implode('/', array_map(static fn (string $s): string => rawurlencode($s), $segments));
    }
}
