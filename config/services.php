<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN (CloudFront 兼容服务)
    |--------------------------------------------------------------------------
    |
    | 支持 Amazon CloudFront 或兼容服务（如 serv-fd 的 app_cdn）。
    | 内容分发 URL: {content_base}/path
    | API 文档: /api/cdn/2020-05-31 (serv-fd 容器)
    |
    */
    'cloudfront' => [
        // 内容分发根 URL，用于 buildCdnUrl。可直接设置或由 api_base + distribution_id 推导
        'domain' => env('AWS_CLOUDFRONT_DOMAIN') ?: (
            (env('CDN_API_BASE_URL') && env('CDN_DISTRIBUTION_ID'))
                ? rtrim(env('CDN_API_BASE_URL'), '/').'/d/'.env('CDN_DISTRIBUTION_ID')
                : ''
        ),
    ],

    'cdn' => [
        // CloudFront 兼容 API 根 URL，如 http://serv-fd:8000/api/cdn/2020-05-31
        'api_base_url' => rtrim((string) env('CDN_API_BASE_URL', ''), '/'),
        // 分发 ID，需先在 CDN 控制台或 API 创建（origin 指向 OSS）
        'distribution_id' => env('CDN_DISTRIBUTION_ID', ''),
    ],
];
