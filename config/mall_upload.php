<?php

$apiGateway = trim((string) env('API_GATEWAY_BASE_URL', ''));
$servFd = $apiGateway !== '' ? $apiGateway : trim((string) env('SERV_FD_BASE_URL', ''));
$explicitOss = trim((string) env('MALL_OSS_UPLOAD_BASE_URL'));
if ($explicitOss) {
    $ossBaseUrl = rtrim($explicitOss, '/');
} else {
    $ossBaseUrl = $servFd ? rtrim($servFd, '/').'/api/oss' : '';
}

return [
    /*
    |--------------------------------------------------------------------------
    | OSS object key prefix (no leading/trailing slash)
    |--------------------------------------------------------------------------
    */
    'prefix' => trim((string) env('MALL_UPLOAD_PREFIX'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Foundation OSS HTTP API (same stack as Django USER_OSS_ENDPOINT)
    |--------------------------------------------------------------------------
    | Defaults to {API_GATEWAY_BASE_URL or SERV_FD_BASE_URL}/api/oss when MALL_OSS_UPLOAD_BASE_URL is unset.
    */
    'oss_base_url' => $ossBaseUrl,

    /*
    | Bucket name for product uploads (PUT /api/oss/{bucket}/{key...}).
    */
    'oss_bucket' => trim((string) env('MALL_OSS_BUCKET', 'mall'), '/'),
];
