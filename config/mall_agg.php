<?php

use App\Services\Mall\Aggregation\LocalProductInventoryProvider;
use App\Services\Mall\Aggregation\LocalProductPriceProvider;

/**
 * Single source for serv-fd base URL: SERV_FD_BASE_URL (falls back).
 */
$servFdBaseUrl = env('SERV_FD_BASE_URL');

return [
    /*
    | api.log_http_errors: 记录所有 api/* 且 HTTP 状态为 4xx、5xx 的响应（含转发下游的「非异常」错误）。
    | api.normalize_5xx_json_body: 为 true 时，将 5xx 的 JSON 响应体替换为统一 errorCode/message（默认关闭，避免掩盖下游语义）。
    */
    'api' => [
        'log_http_errors' => (bool) env('MALL_AGG_API_LOG_HTTP_ERRORS', env('MALL_AGG_API_LOG_HTTP_ERRORS', true)),
        'normalize_5xx_json_body' => (bool) env('MALL_AGG_API_NORMALIZE_5XX_JSON', env('MALL_AGG_API_NORMALIZE_5XX_JSON', false)),
        'normalize_5xx_message' => env('MALL_AGG_API_NORMALIZE_5XX_MESSAGE', env('MALL_AGG_API_NORMALIZE_5XX_MESSAGE', '服务器内部错误')),
    ],

    'serv_fd' => [
        'base_url' => $servFdBaseUrl,
        'timeout_seconds' => (int) env('SERV_FD_TIMEOUT_SECONDS', 3),

        'searchrec' => [
            'base_url' => $servFdBaseUrl . '/api/searchrec',
            'access_key' => (string) env('MALL_SEARCHREC_ACCESS_KEY', ''),
            'timeout_seconds' => (int) env('MALL_SEARCHREC_TIMEOUT_SECONDS', 5),
        ],
    ],

    'cms' => [
        'content_route' => env('MALL_CMS_CONTENT_ROUTE', 'product'),
    ],

    'foundation' => [
        'base_url' => $servFdBaseUrl,
        'me_endpoint' => env('USER_CENTER_ME_ENDPOINT', '/api/user/me'),
        'timeout_seconds' => (int) env('USER_CENTER_TIMEOUT_SECONDS', 3),
        'unauthorized_code' => (int) env('USER_CENTER_UNAUTHORIZED_CODE', 40101),
    ],

    /*
    | ProviderContract 插件（读侧聚合）。以下类仅在 context 含 mall_product_ids 时参与 matched；
    | /api/user/me 不传该字段，故不会触发。订单写操作走 OrderCommandService。
    */
    'business_services' => [
        ['class' => LocalProductPriceProvider::class, 'enabled' => true],
        ['class' => LocalProductInventoryProvider::class, 'enabled' => true],
    ],

    'execution' => [
        'mode' => env('MALL_AGG_EXECUTION_MODE', env('MALL_AGG_EXECUTION_MODE', 'serial')),
    ],

    'degrade' => [
        'strategy' => env('MALL_AGG_DEGRADE_STRATEGY', env('MALL_AGG_DEGRADE_STRATEGY', 'mask_null')),
        'mask_error_message' => env('MALL_AGG_DEGRADE_MASK_ERROR_MESSAGE', env('MALL_AGG_DEGRADE_MASK_ERROR_MESSAGE', 'Service temporarily unavailable.')),
        'partial_failure_code' => (int) env('MALL_AGG_PARTIAL_FAILURE_CODE', env('MALL_AGG_PARTIAL_FAILURE_CODE', 20601)),
        'partial_failure_message' => env('MALL_AGG_PARTIAL_FAILURE_MESSAGE', env('MALL_AGG_PARTIAL_FAILURE_MESSAGE', 'Partially failed, degraded by aggregator.')),
    ],
];
