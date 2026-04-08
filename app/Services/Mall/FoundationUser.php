<?php

declare(strict_types=1);

namespace App\Services\Mall;

use InvalidArgumentException;

final class FoundationUser
{
    /**
     * @param  array<string, mixed>  $user
     */
    public static function id(array $user): int
    {
        $id = $user['id'] ?? $user['user_id'] ?? null;
        if ($id === null || $id === '') {
            throw new InvalidArgumentException('Foundation user payload has no id.');
        }

        return (int) $id;
    }
}
