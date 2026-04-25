<?php

declare(strict_types=1);

namespace App\Services\Adapters;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Paganini\XxlJobExecutor\Interfaces\FileLockInterface;

/**
 * Laravel Storage adapter for XXL-Job executor file locks (same layout as job-executor).
 */
final class XxlJobStorageFileLockAdapter implements FileLockInterface
{
    private const JOB_PATH = 'jobs';

    private const JOB_FILE_SUFFIX = '.job';

    private function buildJobFilePath(string $jobId): string
    {
        return self::JOB_PATH.'/'.$jobId.self::JOB_FILE_SUFFIX;
    }

    public function create(string $jobId): ?string
    {
        $jobFilePath = $this->buildJobFilePath($jobId);
        $storage = Storage::disk('local');
        $has = $storage->put($jobFilePath, $jobId);
        if ($has === false) {
            Log::error('[xxljob] failed to create job file lock at path: '.$jobFilePath);

            return null;
        }
        Log::debug('[xxljob] creating job file lock at path: '.$jobFilePath);

        return $jobFilePath;
    }

    public function delete(string $jobId): bool
    {
        $jobFilePath = $this->buildJobFilePath($jobId);
        $storage = Storage::disk('local');

        if (! $storage->exists($jobFilePath)) {
            Log::warning('[xxljob] job file lock not found at path: '.$jobFilePath.' when trying to delete');

            return true;
        }

        $ret = $storage->delete($jobFilePath);
        Log::debug('[xxljob] deleting job file lock at path: '.$jobFilePath.', success='.($ret ? 'true' : 'false'));

        return $ret;
    }

    public function exists(string $jobId): bool
    {
        $jobFilePath = $this->buildJobFilePath($jobId);
        $storage = Storage::disk('local');
        $ret = $storage->exists($jobFilePath);
        Log::debug('[xxljob] checking existence of job file lock at path: '.$jobFilePath.', exists='.($ret ? 'true' : 'false'));

        return $ret;
    }
}
