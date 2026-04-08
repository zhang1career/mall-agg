<?php

use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    return redirect()->route('admin.products.index');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::resource('products', AdminProductController::class)->except(['show']);
    Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{id}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{id}', [AdminOrderController::class, 'update'])->name('orders.update');
});
