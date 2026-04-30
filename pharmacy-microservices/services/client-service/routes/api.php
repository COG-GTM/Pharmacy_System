<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\AddressController;
use Illuminate\Support\Facades\Route;

Route::get('/clients', [ClientController::class, 'index']);
Route::post('/clients', [ClientController::class, 'store']);
Route::get('/clients/{id}', [ClientController::class, 'show']);
Route::put('/clients/{id}', [ClientController::class, 'update']);
Route::delete('/clients/{id}', [ClientController::class, 'destroy']);

Route::get('/addresses', [AddressController::class, 'index']);
Route::post('/addresses', [AddressController::class, 'store']);
Route::get('/addresses/{id}', [AddressController::class, 'show']);
Route::put('/addresses/{id}', [AddressController::class, 'update']);
Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);
Route::get('/addresses/{id}/validate', [AddressController::class, 'validate_address']);
