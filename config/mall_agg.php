<?php

use App\Services\mall\aggregation\LocalProductInventoryProvider;
use App\Services\mall\aggregation\LocalProductPriceProvider;

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

    'foundation' => [
        'base_url' => env('API_GATEWAY_BASE_URL', ''),
        /*
         * When `base_url` contains `://{{service_key}}` (Fusio-style), resolve via Redis (paganini).
         * Plain URLs skip Redis entirely.
         */
        'service_discovery' => [
            'memo_ttl_seconds' => (int) env('API_GATEWAY_SD_MEMO_TTL', 60),
            'redis_connection' => env('API_GATEWAY_SD_DB_CONN', 'default'),
            'redis_key_prefix' => env('API_GATEWAY_SD_KEY_PREFIX', ''),
        ],
        'me_endpoint' => '/api/user/me',
        'timeout_seconds' => 3,
        'unauthorized_code' => 40101,
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

    'checkout' => [
        'use_coordinators' => (bool) env('MALL_CHECKOUT_USE_COORDINATORS', false),
        'use_tcc_coordinator' => (bool) env('MALL_CHECKOUT_USE_TCC_COORDINATOR', false),
    ],

    /*
    | Pending payment timeout for XXL-Job sweep (milliseconds; order ct/ut use ms).
    */
    'orders' => [
        'pending_payment_timeout_ms' => (int) env('MALL_PENDING_PAYMENT_TIMEOUT_MS', 1_800_000),
    ],

    'internal' => [
        'participant_token' => env('MALL_INTERNAL_PARTICIPANT_TOKEN', ''),
    ],

    'admin' => [
        'api_token' => env('MALL_ADMIN_API_TOKEN', ''),
    ],

    'saga' => [
        'timeout_seconds' => (int) env('MALL_SAGA_TIMEOUT_SECONDS', 10),
        'participant_access_key' => env('MALL_SAGA_PARTICIPANT_ACCESS_KEY', ''),
        'checkout_flow_id' => (int) env('MALL_SAGA_CHECKOUT_FLOW_ID', 0),
    ],

    'tcc' => [
        'timeout_seconds' => (int) env('MALL_TCC_TIMEOUT_SECONDS', 15),
        'branch_meta_points_id' => (int) env('MALL_TCC_BRANCH_META_POINTS_ID', 0),
    ],

    'outbound' => [
        'inventory' => [
            'base_url' => env('MALL_INVENTORY_OUTBOUND_BASE_URL', ''),
            'timeout_seconds' => (int) env('MALL_INVENTORY_OUTBOUND_TIMEOUT_SECONDS', 5),
        ],
    ],
];
