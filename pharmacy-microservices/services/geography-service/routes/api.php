<?php

use App\Http\Controllers\AreaController;
use Illuminate\Support\Facades\Route;

Route::get('/areas', [AreaController::class, 'index']);
Route::post('/areas', [AreaController::class, 'store']);
Route::get('/areas/{id}', [AreaController::class, 'show']);
Route::put('/areas/{id}', [AreaController::class, 'update']);
Route::delete('/areas/{id}', [AreaController::class, 'destroy']);
