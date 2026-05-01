<?php

use App\Http\Controllers\admin\AdminOrderController;
use App\Http\Controllers\admin\AdminPointsController;
use App\Http\Controllers\admin\AdminProductController;
use App\Http\Controllers\admin\AdminUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    return redirect()->route('admin.products.index');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::post('uploads', [AdminUploadController::class, 'store'])->name('uploads.store');
    Route::resource('products', AdminProductController::class);
    Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{id}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{id}', [AdminOrderController::class, 'update'])->name('orders.update');
    Route::delete('orders/{id}', [AdminOrderController::class, 'destroy'])->name('orders.destroy');

    Route::get('points', [AdminPointsController::class, 'index'])->name('points.index');
    Route::get('points/balances/{id}', [AdminPointsController::class, 'showBalance'])->name('points.balances.show');
    Route::delete('points/balances/{id}', [AdminPointsController::class, 'destroyBalance'])->name('points.balances.destroy');
    Route::get('points/flows/{id}', [AdminPointsController::class, 'showFlow'])->name('points.flows.show');
    Route::delete('points/flows/{id}', [AdminPointsController::class, 'destroyFlow'])->name('points.flows.destroy');
    Route::post('points/accounts', [AdminPointsController::class, 'storeAccount'])->name('points.accounts.store');
    Route::post('points/adjust', [AdminPointsController::class, 'adjust'])->name('points.adjust');
});
