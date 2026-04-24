<?php

declare(strict_types=1);

use App\Http\Controllers\Internal\InventoryParticipantController;
use App\Http\Controllers\Internal\OrderParticipantController;
use App\Http\Controllers\Internal\PayParticipantController;
use App\Http\Controllers\Internal\TccPointsParticipantController;
use Illuminate\Support\Facades\Route;

Route::post('inventory/action', [InventoryParticipantController::class, 'action']);
Route::post('inventory/compensate', [InventoryParticipantController::class, 'compensate']);

Route::post('order/action', [OrderParticipantController::class, 'action']);
Route::post('order/compensate', [OrderParticipantController::class, 'compensate']);

Route::post('points/try', [TccPointsParticipantController::class, 'try']);
Route::post('points/confirm', [TccPointsParticipantController::class, 'confirm']);
Route::post('points/cancel', [TccPointsParticipantController::class, 'cancel']);

Route::post('pay/action', [PayParticipantController::class, 'action']);
Route::post('pay/compensate', [PayParticipantController::class, 'compensate']);
