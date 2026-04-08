<?php

namespace App\Queue;

use App\Queue\Jobs\DatabaseJobRecordMillis;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Queue\DatabaseQueue;

class DatabaseQueueMillis extends DatabaseQueue
{
    /**
     * Get the current system time as Unix timestamp in milliseconds.
     *
     * @return int
     */
    protected function currentTime()
    {
        return (int) floor(microtime(true) * 1000);
    }

    /**
     * Get the "available at" UNIX timestamp in milliseconds.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @return int
     */
    protected function availableAt($delay = 0)
    {
        $delay = $this->parseDateInterval($delay);

        return $delay instanceof \DateTimeInterface
            ? (int) ($delay->getTimestamp() * 1000)
            : (int) (Carbon::now()->addRealSeconds($delay)->getTimestamp() * 1000);
    }

    /**
     * Create an array to insert for the given job.
     *
     * @param  string|null  $queue
     * @param  string  $payload
     * @param  int  $availableAt
     * @param  int  $attempts
     * @return array
     */
    protected function buildDatabaseRecord($queue, $payload, $availableAt, $attempts = 0)
    {
        return [
            'queue' => $queue,
            'attempts' => $attempts,
            'reserved_at' => 0, // 0 = not reserved
            'available_at' => $availableAt,
            'ct' => $this->currentTime(),
            'payload' => $payload,
        ];
    }

    /**
     * Modify the query to check for jobs that are reserved but have expired.
     *
     * @param  Builder  $query
     * @return void
     */
    /**
     * Modify the query to check for available jobs (reserved_at = 0 means not reserved).
     *
     * @param  Builder  $query
     * @return void
     */
    protected function isAvailable($query)
    {
        $query->where(function ($query) {
            $query->where('reserved_at', 0)
                ->where('available_at', '<=', $this->currentTime());
        });
    }

    /**
     * Modify the query to check for jobs that are reserved but have expired.
     *
     * @param  Builder  $query
     * @return void
     */
    protected function isReservedButExpired($query)
    {
        $expiration = $this->currentTime() - ($this->retryAfter * 1000);

        $query->orWhere(function ($query) use ($expiration) {
            $query->where('reserved_at', '>', 0)
                ->where('reserved_at', '<=', $expiration);
        });
    }

    /**
     * Get the next available job for the queue.
     *
     * @param  string|null  $queue
     * @return DatabaseJobRecordMillis|null
     */
    protected function getNextAvailableJob($queue)
    {
        $job = $this->database->table($this->table)
            ->lock($this->getLockForPopping())
            ->where('queue', $this->getQueue($queue))
            ->where(function ($query) {
                $this->isAvailable($query);
                $this->isReservedButExpired($query);
            })
            ->orderBy('id', 'asc')
            ->first();

        return $job ? new DatabaseJobRecordMillis((object) $job) : null;
    }
}
