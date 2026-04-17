<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OSS object key prefix (no leading/trailing slash)
    |--------------------------------------------------------------------------
    */
    'prefix' => trim((string) env('MALL_UPLOAD_PREFIX'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Bucket name for product uploads (PUT /api/oss/{bucket}/{key...}).
    | OSS API base is runtime {API_GATEWAY_BASE_URL}/api/oss (see MallOssUploadService).
    |--------------------------------------------------------------------------
    */
    'oss_bucket' => trim((string) env('MALL_OSS_BUCKET', 'mall'), '/'),
];
