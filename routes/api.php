<?php

use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

Route::post('/orders', [OrderController::class, 'store']);
Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus'])->whereNumber('id');
Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
