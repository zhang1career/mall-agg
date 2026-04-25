<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use RuntimeException;

final class CoordinatorEnvelope
{
    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    public static function dataOrFail(?array $json, string $label): array
    {
        if ($json === null) {
            throw new RuntimeException($label.': empty JSON response.');
        }
        $code = (int) ($json['errorCode'] ?? -1);
        if ($code !== 0) {
            $msg = (string) ($json['message'] ?? 'coordinator error');

            throw new RuntimeException($label.': '.$msg.' (errorCode='.$code.')');
        }
        $data = $json['data'] ?? [];
        if (! is_array($data)) {
            throw new RuntimeException($label.': invalid data payload.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
