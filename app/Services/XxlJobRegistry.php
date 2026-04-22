<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Paganini\XxlJobExecutor\Attributes\XxlJob;
use Paganini\XxlJobExecutor\JobRegistry as PaganiniXxlJobRegistry;
use ReflectionClass;
use ReflectionMethod;

/**
 * 扫描 `app/Jobs` 下带 #[XxlJob] 的静态方法并注册。
 */
final class XxlJobRegistry extends PaganiniXxlJobRegistry
{
    public function scanAndRegister(string $jobPath = 'Jobs'): void
    {
        $basePath = app_path($jobPath);
        if (! is_dir($basePath)) {
            Log::warning('[jobreg] job directory not found: '.$basePath);

            return;
        }

        $files = glob($basePath.'/*.php') ?: [];
        foreach ($files as $file) {
            $this->scanFile($file);
        }

        Log::info('[jobreg] registered '.count($this->getAllJobs()).' jobs');
    }

    private function scanFile(string $filePath): void
    {
        $className = $this->getClassNameFromFile($filePath);
        if ($className === null || ! class_exists($className)) {
            Log::warning('[jobreg] class not found for file: '.$filePath);

            return;
        }

        $reflection = new ReflectionClass($className);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC) as $method) {
            foreach ($method->getAttributes(XxlJob::class) as $attribute) {
                $handler = $attribute->newInstance()->handler;

                if ($this->hasJob($handler)) {
                    Log::warning("[jobreg] duplicate handler '{$handler}', overwriting.");
                }

                parent::register($handler, [$className, $method->getName()]);
            }
        }
    }

    private function getClassNameFromFile(string $filePath): ?string
    {
        $appPath = app_path();
        if (! str_starts_with($filePath, $appPath)) {
            return null;
        }

        $relativePath = substr($filePath, strlen($appPath) + 1);
        $relativePath = str_replace(['.php', '/'], ['', '\\'], $relativePath);

        return 'App\\'.$relativePath;
    }
}
