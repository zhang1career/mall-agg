<?php

use App\Http\Controllers\Api\MallCheckoutController;
use App\Http\Controllers\Api\MallOrderController;
use App\Http\Controllers\Api\MallProductController;
use App\Http\Controllers\Api\PaymentCallbackController;
use App\Http\Controllers\UserAggregationController;
use Illuminate\Support\Facades\Route;

Route::prefix('')->middleware([])->group(function () {
    Route::get('user/me', [UserAggregationController::class, 'me']);

    Route::prefix('mall')->group(function () {
        Route::get('products', [MallProductController::class, 'index']);
        Route::post('products/search', [MallProductController::class, 'search']);
        Route::get('products/{id}', [MallProductController::class, 'show'])->whereNumber('id');
        Route::post('orders', [MallOrderController::class, 'store']);
        Route::patch('orders/{id}', [MallOrderController::class, 'update'])->whereNumber('id');
        Route::get('orders', [MallOrderController::class, 'index']);
        Route::get('orders/{id}', [MallOrderController::class, 'show'])->whereNumber('id');
        Route::post('checkout', [MallCheckoutController::class, 'store']);
        Route::post('payment/callback', PaymentCallbackController::class);
    });
});
