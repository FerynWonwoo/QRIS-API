<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\QrisTransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn() => ['message' => 'API QRIS Kelompok 4 berjalan']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::get('/me', function () {
        return response()->json([
            'success' => true,
            'message' => 'Profil pengguna',
            'data'    => auth('api')->user()->only('id', 'name', 'email', 'role'),
        ]);
    });

    Route::get('/qris-transactions', [QrisTransactionController::class, 'index']);
    Route::get('/qris-transactions/{id}', [QrisTransactionController::class, 'show'])->whereNumber('id');
    Route::post('/qris-transactions', [QrisTransactionController::class, 'store']);

    Route::middleware('role:admin')->group(function () {
        Route::match(['put', 'patch'], '/qris-transactions/{id}', [QrisTransactionController::class, 'update'])->whereNumber('id');
        Route::delete('/qris-transactions/{id}', [QrisTransactionController::class, 'destroy'])->whereNumber('id');
    });
});