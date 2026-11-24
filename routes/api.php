
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\QrisTransactionController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\Api\QrisCodeController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/ping', fn() => [
    'message' => 'API QRIS Kelompok 4 berjalan'
]);

Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES - JWT AUTHENTICATION
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | PROFIL PENGGUNA
    |--------------------------------------------------------------------------
    */

    Route::get('/me', function () {
        return response()->json([
            'success' => true,
            'message' => 'Profil pengguna',
            'data' => auth('api')->user()
                ->only('id', 'name', 'email', 'role'),
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | CRUD MERCHANT
    |--------------------------------------------------------------------------
    | User: Create, Read, Update
    | Admin: Create, Read, Update, Delete
    */

    Route::apiResource('merchants', MerchantController::class)
        ->except(['destroy']);

    /*
    |--------------------------------------------------------------------------
    | CRUD QRIS CODES DINAMIS
    |--------------------------------------------------------------------------
    | User: Create, Read, Update
    | Admin: Create, Read, Update, Delete
    */

    Route::apiResource('qris-codes', QrisCodeController::class)
        ->except(['destroy']);

    /*
    |--------------------------------------------------------------------------
    | CRUD SETTLEMENT
    |--------------------------------------------------------------------------
    | User: Create, Read, Update
    | Admin: Create, Read, Update, Delete
    */

    Route::apiResource('settlements', SettlementController::class)
        ->except(['destroy']);

    /*
    |--------------------------------------------------------------------------
    | CRUD TRANSAKSI QRIS
    |--------------------------------------------------------------------------
    | User: Create, Read, Update
    | Admin: Create, Read, Update, Delete
    */

    Route::get(
        '/qris-transactions',
        [QrisTransactionController::class, 'index']
    );

    Route::get(
        '/qris-transactions/{id}',
        [QrisTransactionController::class, 'show']
    )->whereNumber('id');

    Route::post(
        '/qris-transactions',
        [QrisTransactionController::class, 'store']
    );

    Route::match(
        ['put', 'patch'],
        '/qris-transactions/{id}',
        [QrisTransactionController::class, 'update']
    )->whereNumber('id');

    /*
    |--------------------------------------------------------------------------
    | DELETE KHUSUS ADMIN - RBAC
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')->group(function () {

        Route::delete(
            '/merchants/{merchant}',
            [MerchantController::class, 'destroy']
        );

        Route::delete(
            '/qris-codes/{qris_code}',
            [QrisCodeController::class, 'destroy']
        );

        Route::delete(
            '/settlements/{settlement}',
            [SettlementController::class, 'destroy']
        );

        Route::delete(
            '/qris-transactions/{id}',
            [QrisTransactionController::class, 'destroy']
        )->whereNumber('id');

    });

});
