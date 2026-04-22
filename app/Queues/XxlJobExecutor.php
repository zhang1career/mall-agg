<?php

declare(strict_types=1);

namespace App\Queues;

use App\Services\Adapters\XxlJobGuzzleCallbackClientAdapter;
use App\Services\Adapters\XxlJobStorageFileLockAdapter;
use App\Services\api_gw\ResolvedXxlJobAdminAddress;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Paganini\XxlJobExecutor\JobExecutionHandler;

/**
 * XXL-Job 业务执行体；由 {@see XxlJobController} 通过 {@see dispatchSync()} 同步调用（不入队、不依赖 jobs 表）。
 */
final class XxlJobExecutor
{
    use Dispatchable;

    /**
     * @param  array{0: class-string, 1: string}  $callable
     */
    public function __construct(
        private readonly array $callable,
        private readonly mixed $param,
        private readonly int $logId,
        private readonly string $filePath,
    ) {}

    public function handle(ResolvedXxlJobAdminAddress $resolvedAdmin): void
    {
        Log::debug('[xxljob] callback start, logId='.$this->logId);

        $executionHandler = new JobExecutionHandler(
            new XxlJobGuzzleCallbackClientAdapter(
                $resolvedAdmin->resolve(),
                (string) config('xxl.token'),
            ),
            new XxlJobStorageFileLockAdapter,
        );

        $jobId = basename($this->filePath, '.job');
        $executionHandler->execute(
            $this->callable,
            $this->param,
            $this->logId,
            $jobId,
        );
    }
}
