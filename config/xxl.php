<?php

declare(strict_types=1);

return [
    'admin_address' => rtrim(
        env('XXL_JOB_ADMIN_ADDRESS', 'http://xxl-job:8080/xxl-job-admin'),
        '/'
    ),
    'token' => env('XXL_JOB_TOKEN', ''),
];
