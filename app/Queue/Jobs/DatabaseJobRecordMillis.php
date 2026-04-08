<?php

namespace App\Queue\Jobs;

use Illuminate\Queue\Jobs\DatabaseJobRecord;

class DatabaseJobRecordMillis extends DatabaseJobRecord
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
}
