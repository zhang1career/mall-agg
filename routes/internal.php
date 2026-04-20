<?php

declare(strict_types=1);

use App\Http\Controllers\Internal\TccPointsParticipantController;
use Illuminate\Support\Facades\Route;

Route::post('tcc/points/try', [TccPointsParticipantController::class, 'try']);
Route::post('tcc/points/confirm', [TccPointsParticipantController::class, 'confirm']);
Route::post('tcc/points/cancel', [TccPointsParticipantController::class, 'cancel']);
