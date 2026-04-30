<?php

use App\Http\Controllers\RevenueController;
use App\Http\Controllers\ChartController;
use Illuminate\Support\Facades\Route;

Route::get('/revenue', [RevenueController::class, 'index']);
Route::get('/charts/status', [ChartController::class, 'statusData']);
