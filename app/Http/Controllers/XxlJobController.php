<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Components\XxlResponse;
use App\Queues\XxlJobExecutor;
use App\Services\Adapters\XxlJobStorageFileLockAdapter;
use App\Services\XxlJobRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Paganini\XxlJobExecutor\JobFileLock;
use Paganini\XxlJobExecutor\JobRequest;
use Paganini\XxlJobExecutor\JobRequestHandler;

/**
 * XXL-Job executor HTTP surface (aligned with job-executor). Base path: /api/xxl-job.
 */
final class XxlJobController
{
    public function __construct(
        private readonly XxlJobRegistry $jobRegistry,
    ) {}

    /**
     * @return array{data: mixed, code: int, msg: string}
     */
    public function beat(): array
    {
        return XxlResponse::success();
    }

    /**
     * @return array{data: mixed, code: int, msg: string}
     */
    public function run(Request $request): array
    {
        $requestData = $request->post();
        Log::debug('[xxljob] param: request=', ['body' => $requestData]);

        $logDateTim = (int) ($requestData['logDateTime'] ?? 0);
        if ($logDateTim <= 0) {
            return XxlResponse::fail('Invalid logDateTime');
        }

        $requestJob = JobRequest::fromArray($requestData);

        $fileLock = new JobFileLock(new XxlJobStorageFileLockAdapter);
        $requestHandler = new JobRequestHandler(
            $this->jobRegistry,
            $fileLock,
        );

        $acceptedJob = $requestHandler->handle($requestJob);
        if (! $acceptedJob->isSuccess()) {
            return XxlResponse::fail($acceptedJob->getMessage() ?? 'Job execution failed');
        }

        $jobData = $acceptedJob->getData();
        $job = $jobData['job'];
        $params = $jobData['params'];
        $logId = $jobData['logId'];
        $filePath = $jobData['filePath'];

        XxlJobExecutor::dispatchSync($job, $params, $logId, $logDateTim, $filePath);

        return XxlResponse::success();
    }

    /**
     * @return array{data: mixed, code: int, msg: string}
     */
    public function kill(Request $request): array
    {
        $body = $request->post();
        Log::debug('[xxljob] kill param: request=', ['body' => $body]);
        $jobId = (string) ($body['jobId'] ?? '');

        $fileLock = new JobFileLock(new XxlJobStorageFileLockAdapter);
        if (! $fileLock->exists($jobId)) {
            Log::info('[xxljob] job file not exists, jobId='.$jobId);

            return XxlResponse::success(null, 'job file not exists, jobId='.$jobId);
        }

        $fileLock->delete($jobId);
        Log::debug('[xxljob] job killed, jobId='.$jobId);

        return XxlResponse::success();
    }
}
