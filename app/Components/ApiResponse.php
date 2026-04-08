<?php

namespace App\Components;

class ApiResponse
{
    /**
     * @param  null  $data
     */
    public static function ok($data = null, string $msg = '', string $reqId = ''): array
    {
        return [
            'data' => $data ?? '',
            'errorCode' => 0,
            'message' => $msg,
            '_req_id' => $reqId,
        ];
    }

    public static function error(int $code, string $msg, string $reqId = ''): array
    {
        return [
            'data' => '',
            'errorCode' => $code,
            'message' => $msg,
            '_req_id' => $reqId,
        ];
    }

    public static function code(mixed $data, int $code, string $msg = '', string $reqId = ''): array
    {
        return [
            'data' => $data ?? '',
            'errorCode' => $code,
            'message' => $msg,
            '_req_id' => $reqId,
        ];
    }
}
