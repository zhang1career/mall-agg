<?php

use App\Components\ApiResponse;
use App\Http\Middleware\LogApiHttpErrors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Paganini\Env\LayeredEnvLoader;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->appendToGroup('api', LogApiHttpErrors::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! str_starts_with($request->path(), 'api/')) {
                return null;
            }

            $reqId = $request->header('X-Request-Id') ?: bin2hex(random_bytes(8));

            if ($exception instanceof ValidationException) {
                $message = $exception->validator->errors()->first() ?: 'Validation failed.';

                return response()->json(
                    ApiResponse::error(1, $message, $reqId),
                    422
                );
            }

            Log::error('Uncaught API exception', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                '_req_id' => $reqId,
            ]);

            $publicMessage = config('app.debug') ? $exception->getMessage() : '服务器内部错误';

            return response()->json(
                ApiResponse::error(2, $publicMessage, $reqId),
                500
            );
        });
    })->create();

// Base `.env` is loaded by Laravel; optional `.env.{dev|test|prod}` overlay (paganini), aligned with user-agg.
$app->afterLoadingEnvironment(function ($application): void {
    $seg = getenv('APP_ENV');
    if ($seg === false || $seg === '') {
        return;
    }
    $seg = trim($seg);
    if (! in_array($seg, ['dev', 'test', 'prod'], true)) {
        return;
    }
    LayeredEnvLoader::loadEnvironmentOverlay(
        $application->environmentPath(),
        $application->environmentFile()
    );
});

return $app;
