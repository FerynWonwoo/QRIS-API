
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\QrisTransactionController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\QrisCodeController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
| Endpoint yang dapat diakses tanpa login
*/

// Mengecek apakah API berjalan
Route::get('/ping', function () {
    return response()->json([
        'success' => true,
        'message' => 'API QRIS Kelompok 4 berjalan',
    ]);
});

// Login pengguna
Route::post('/login', [AuthController::class, 'login']);


/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES - JWT AUTHENTICATION
|--------------------------------------------------------------------------
| Semua endpoint membutuhkan token JWT
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
            'data' => auth('api')->user()->only(
                'id',
                'name',
                'email',
                'role'
            ),
        ]);
    });


    /*
    |--------------------------------------------------------------------------
    | CRUD MANAJEMEN MERCHANT
    |--------------------------------------------------------------------------
    | Anggota 1
    | Akses: Admin dan Merchant
    */

    Route::middleware('role:admin,merchant')->group(function () {

        Route::apiResource(
            'merchants',
            MerchantController::class
        );

    });


    /*
    |--------------------------------------------------------------------------
    | CRUD QRIS CODES DINAMIS
    |--------------------------------------------------------------------------
    | Membuat kode QRIS unik setiap transaksi
    | Akses: Admin dan Merchant
    */

    Route::middleware('role:admin,merchant')->group(function () {

        Route::apiResource(
            'qris-codes',
            QrisCodeController::class
        );

    });


    /*
    |--------------------------------------------------------------------------
    | CRUD TRANSAKSI QRIS
    |--------------------------------------------------------------------------
    | Fitur milik anggota lain
    */

    // GET seluruh transaksi QRIS
    Route::get(
        '/qris-transactions',
        [QrisTransactionController::class, 'index']
    );

    // GET detail transaksi QRIS
    Route::get(
        '/qris-transactions/{id}',
        [QrisTransactionController::class, 'show']
    )->whereNumber('id');

    // POST transaksi baru
    Route::post(
        '/qris-transactions',
        [QrisTransactionController::class, 'store']
    );


    /*
    |--------------------------------------------------------------------------
    | TRANSAKSI KHUSUS ADMIN - RBAC
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')->group(function () {

        // PUT / PATCH transaksi QRIS
        Route::match(
            ['put', 'patch'],
            '/qris-transactions/{id}',
            [QrisTransactionController::class, 'update']
        )->whereNumber('id');

        // DELETE transaksi QRIS
        Route::delete(
            '/qris-transactions/{id}',
            [QrisTransactionController::class, 'destroy']
        )->whereNumber('id');

    });

});
