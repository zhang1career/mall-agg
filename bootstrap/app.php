<?php

use App\Components\ApiResponse;
use App\Http\Middleware\LogApiHttpErrors;
use App\Http\Middleware\VerifyAdminApiToken;
use App\Http\Middleware\VerifyInternalParticipantToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Paganini\Env\LayeredEnvLoader;
use Symfony\Component\HttpKernel\Exception\HttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(['api', VerifyInternalParticipantToken::class])
                ->prefix('internal')
                ->group(base_path('routes/internal.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->appendToGroup('api', LogApiHttpErrors::class);
        $middleware->alias([
            'internal.participant' => VerifyInternalParticipantToken::class,
            'admin.api' => VerifyAdminApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $exception, Request $request) {
            $path = $request->path();
            if (! str_starts_with($path, 'api/') && ! str_starts_with($path, 'internal/')) {
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

            if ($exception instanceof HttpException) {
                $status = $exception->getStatusCode();

                return response()->json(
                    ApiResponse::error($status, $exception->getMessage() ?: 'HTTP error', $reqId),
                    $status
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
