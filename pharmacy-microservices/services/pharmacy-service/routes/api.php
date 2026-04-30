<?php

use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\DoctorController;
use Illuminate\Support\Facades\Route;

Route::get('/pharmacies', [PharmacyController::class, 'index']);
Route::post('/pharmacies', [PharmacyController::class, 'store']);
Route::get('/pharmacies/{id}', [PharmacyController::class, 'show']);
Route::put('/pharmacies/{id}', [PharmacyController::class, 'update']);
Route::delete('/pharmacies/{id}', [PharmacyController::class, 'destroy']);
Route::get('/pharmacies/restore/{id}', [PharmacyController::class, 'restore']);
Route::get('/pharmacies/by-area/{areaId}', [PharmacyController::class, 'byArea']);

Route::get('/doctors', [DoctorController::class, 'index']);
Route::post('/doctors', [DoctorController::class, 'store']);
Route::get('/doctors/{id}', [DoctorController::class, 'show']);
Route::put('/doctors/{id}', [DoctorController::class, 'update']);
Route::delete('/doctors/{id}', [DoctorController::class, 'destroy']);
Route::post('/doctors/{id}/ban', [DoctorController::class, 'ban']);
Route::post('/doctors/{id}/unban', [DoctorController::class, 'unban']);
