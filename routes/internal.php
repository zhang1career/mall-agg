<?php

declare(strict_types=1);

use App\Http\Controllers\internal\InventoryParticipantController;
use App\Http\Controllers\internal\OrderParticipantController;
use App\Http\Controllers\internal\PayParticipantController;
use App\Http\Controllers\internal\TccPointsParticipantController;
use Illuminate\Support\Facades\Route;

Route::post('inventory/action', [InventoryParticipantController::class, 'action']);
Route::post('inventory/compensate', [InventoryParticipantController::class, 'compensate']);

Route::post('order/action', [OrderParticipantController::class, 'action']);
Route::post('order/compensate', [OrderParticipantController::class, 'compensate']);

Route::post('points/try', [TccPointsParticipantController::class, 'try']);
Route::post('points/confirm', [TccPointsParticipantController::class, 'confirm']);
Route::post('points/cancel', [TccPointsParticipantController::class, 'cancel']);

Route::post('pay/try', [PayParticipantController::class, 'try']);
Route::post('pay/confirm', [PayParticipantController::class, 'confirm']);
Route::post('pay/cancel', [PayParticipantController::class, 'cancel']);
