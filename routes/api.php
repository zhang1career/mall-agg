<?php

use App\Http\Controllers\Api\MallAdminPointsController;
use App\Http\Controllers\Api\MallCheckoutController;
use App\Http\Controllers\Api\MallDictController;
use App\Http\Controllers\Api\MallOrderController;
use App\Http\Controllers\Api\MallPointsController;
use App\Http\Controllers\Api\MallProductController;
use App\Http\Controllers\Api\PaymentCallbackController;
use App\Http\Controllers\UserAggregationController;
use App\Http\Controllers\XxlJobController;
use App\Http\Middleware\XxljobAuthentication;
use Illuminate\Support\Facades\Route;

Route::prefix('xxl-job')->middleware([XxljobAuthentication::class])->group(function () {
    Route::get('beat', [XxlJobController::class, 'beat']);
    Route::post('run', [XxlJobController::class, 'run']);
    Route::post('kill', [XxlJobController::class, 'kill']);
});

Route::prefix('')->middleware([])->group(function () {
    Route::get('user/me', [UserAggregationController::class, 'me']);

    Route::prefix('mall')->group(function () {
        Route::get('dict', MallDictController::class);
        Route::get('products', [MallProductController::class, 'index']);
        Route::post('products/search', [MallProductController::class, 'search']);
        Route::get('products/{id}', [MallProductController::class, 'show'])->whereNumber('id');
        Route::post('orders', [MallOrderController::class, 'store']);
        Route::patch('orders/{id}', [MallOrderController::class, 'update'])->whereNumber('id');
        Route::get('orders', [MallOrderController::class, 'index']);
        Route::get('orders/{id}', [MallOrderController::class, 'show'])->whereNumber('id');
        Route::get('points', [MallPointsController::class, 'show']);
        Route::post('checkout', [MallCheckoutController::class, 'store']);
        Route::post('payment/callback', PaymentCallbackController::class);

        Route::prefix('admin')->middleware(['admin.api'])->group(function () {
            Route::post('points/accounts', [MallAdminPointsController::class, 'storeAccount']);
            Route::post('points/adjust', [MallAdminPointsController::class, 'adjust']);
        });
    });
});
