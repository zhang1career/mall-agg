<?php

declare(strict_types=1);

namespace App\Components;

final class XxlResponse
{
    /**
     * @return array{data: mixed, code: int, msg: string}
     */
    public static function success(mixed $data = null, string $msg = ''): array
    {
        return [
            'data' => $data,
            'code' => 200,
            'msg' => $msg,
        ];
    }

    /**
     * @return array{data: null, code: int, msg: string}
     */
    public static function fail(string $message): array
    {
        return [
            'data' => null,
            'code' => 500,
            'msg' => $message,
        ];
    }
}
