<?php

use App\Exceptions\ApiJsonExceptionHandler;
use App\Http\Middleware\LogApiHttpErrors;
use App\Http\Middleware\VerifyAdminApiToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Paganini\Env\LayeredEnvLoader;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(['api'])
                ->prefix('internal')
                ->group(base_path('routes/internal.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->appendToGroup('api', LogApiHttpErrors::class);
        $middleware->alias([
            'admin.api' => VerifyAdminApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $exception, Request $request) {
            return ApiJsonExceptionHandler::render($request, $exception);
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
