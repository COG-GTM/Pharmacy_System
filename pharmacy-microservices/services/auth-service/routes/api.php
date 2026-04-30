<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'getToken']);
Route::get('/email/resend/{id}', [AuthController::class, 'resend']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users/{id}', [AuthController::class, 'show']);
    Route::post('/users', [AuthController::class, 'store']);
    Route::put('/users/{id}', [AuthController::class, 'update']);
    Route::delete('/users/{id}', [AuthController::class, 'destroy']);
    Route::post('/users/{id}/ban', [AuthController::class, 'ban']);
    Route::post('/users/{id}/unban', [AuthController::class, 'unban']);
});
