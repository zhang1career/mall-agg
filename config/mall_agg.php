<?php

use App\Services\mall\aggregation\LocalProductInventoryProvider;
use App\Services\mall\aggregation\LocalProductPriceProvider;
use Paganini\Constants\ResponseConstant;

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
            'redis_connection' => env('API_GATEWAY_SD_DB_CONN', 'default'),
            'redis_key_prefix' => env('API_GATEWAY_SD_KEY_PREFIX', ''),
        ],
        'me_endpoint' => '/api/user/me',
        'timeout_seconds' => 3,
        'unauthorized_code' => (int) env(
            'MALL_AGG_FOUNDATION_UNAUTHORIZED_CODE',
            ResponseConstant::RET_UNAUTHORIZED
        ),
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
        'partial_failure_code' => (int) env(
            'MALL_AGG_PARTIAL_FAILURE_CODE',
            ResponseConstant::RET_DEPENDENCY_ERROR
        ),
        'partial_failure_message' => env('MALL_AGG_PARTIAL_FAILURE_MESSAGE', env('MALL_AGG_PARTIAL_FAILURE_MESSAGE', 'Partially failed, degraded by aggregator.')),
    ],

    /*
    | POST /api/mall/payment/callback: optional shared secret via X-Payment-Callback-Token.
    */
    'payment' => [
        'callback_token' => env('MALL_PAYMENT_CALLBACK_TOKEN', ''),
    ],

    /*
    | Pending payment timeout for XXL-Job sweep (milliseconds; order ct/ut use ms).
    */
    'orders' => [
        'pending_payment_timeout_ms' => (int) env('MALL_PENDING_PAYMENT_TIMEOUT_MS', 1_800_000),
    ],

    'admin' => [
        'api_token' => env('MALL_ADMIN_API_TOKEN', ''),
    ],

    /*
    | Saga coordinator (POST /api/saga/instances). Checkout starts the flow; step_payloads use checkout_steps keys.
    | MALL_SAGA_* required; MALL_TCC_FLOW_ID as pay step payload.biz_id. Idempotency: X-Request-Id on saga start.
    | need_confirm must carry prepay (pay step is_need_confirm); TccBranchMeta.code must match tcc.checkout_branches.
    */
    'saga' => [
        'timeout_seconds' => (int) env('MALL_SAGA_TIMEOUT_SECONDS', 10),
        'access_key' => env('MALL_SAGA_ACCESS_KEY', ''),
        'flow_id' => (int) env('MALL_SAGA_FLOW_ID', 0),
        /*
        | Checkout flow step_payloads keys (match SagaFlowStep.step_code in coordinator).
        */
        'checkout_steps' => [
            'inventory' => env('MALL_SAGA_CHECKOUT_STEP_INVENTORY', 'inventory'),
            'order' => env('MALL_SAGA_CHECKOUT_STEP_ORDER', 'order'),
            'pay' => env('MALL_SAGA_CHECKOUT_STEP_PAY', 'pay'),
        ],
    ],

    'tcc' => [
        'timeout_seconds' => (int) env('MALL_TCC_TIMEOUT_SECONDS', 15),
        'flow_id' => (int) env('MALL_TCC_FLOW_ID', 0),
        /*
        | TCC biz (MALL_TCC_FLOW_ID) branch codes for checkout pay step — must match DB TccBranchMeta.code for that biz.
        */
        'checkout_branches' => [
            'try_points' => env('MALL_TCC_BRANCH_TRY_POINTS', 'try_points'),
            'prepay' => env('MALL_TCC_BRANCH_PREPAY', 'prepay'),
        ],
    ],

    /*
    | Inventory external reserve/release (HTTP) is not wired yet. Code depends on
    | InventoryOutboundContract; container binds StubInventoryOutboundClient. When a real
    | inventory service exists, add config keys and a client implementation, then rebind.
    */
];
