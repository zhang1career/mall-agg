<?php

return [
    'base_url' => env('API_GATEWAY_BASE_URL', ''),
    'timeout_seconds' => (int)env('API_GATEWAY_TIMEOUT_SECONDS', 3),

    'cms' => [
        'cms_url' => env('API_GATEWAY_CMS_URL', '/api/cms/'),
        'content_route' => env('MALL_CMS_CONTENT_ROUTE', ''),
    ],

    'searchrec' => [
        'access_key' => (string)env('MALL_SEARCHREC_ACCESS_KEY', ''),
        'timeout_seconds' => 5,
    ],
];
