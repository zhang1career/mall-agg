<?php

declare(strict_types=1);

namespace App\Infrastructure\ServiceDiscovery;

use Illuminate\Redis\Connections\Connection;
use Paganini\ServiceDiscovery\Contracts\RedisStringClient;

/**
 * Laravel Redis connection adapter for {@see RedisStringClient} (phpredis semantics).
 */
final class LaravelRedisStringClient implements RedisStringClient
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function get(string $key): string|false
    {
        $v = $this->connection->get($key);
        if ($v === null || $v === false) {
            return false;
        }

        return (string) $v;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string|false>
     */
    public function mget(array $keys): array
    {
        if ($keys === []) {
            return [];
        }
        $values = $this->connection->mget($keys);
        $out = [];
        foreach ($values as $v) {
            $out[] = ($v === null || $v === false) ? false : (string) $v;
        }

        return $out;
    }
}
