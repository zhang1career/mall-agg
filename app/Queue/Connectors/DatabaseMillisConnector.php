<?php

namespace App\Queue\Connectors;

use App\Queue\DatabaseQueueMillis;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\DatabaseConnector;

class DatabaseMillisConnector extends DatabaseConnector
{
    /**
     * Establish a queue connection.
     *
     * @return Queue
     */
    public function connect(array $config)
    {
        return new DatabaseQueueMillis(
            $this->connections->connection($config['connection'] ?? null),
            $config['table'],
            $config['queue'],
            $config['retry_after'] ?? 60,
            $config['after_commit'] ?? null
        );
    }
}
