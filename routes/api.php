<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('', function () {
    return response()->json([
        "message" => "Go to /api/documentation for the API Documentation"
    ], 200, [], JSON_UNESCAPED_SLASHES);
});


// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/register/penitip', [AuthController::class, 'register_penitip'])->name('auth.register.penitip');
    Route::post('/register/pengguna', [AuthController::class, 'register_pengguna'])->name('auth.register.pengguna');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout')->middleware('auth:sanctum');
    Route::post('/otp', [AuthController::class, 'sendOtp'])->name('auth.sendOtp');
    Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->name('auth.verifyOtp');

});

// Admin Routes
Route::prefix('admin')->group(function () {
    Route::post('/user', [AdminController::class, 'create_pengguna'])->name('admin.create.pengguna')->middleware('auth:sanctum');
    Route::delete('/user', [AdminController::class, 'delete_pengguna'])->name('admin.delete.pengguna')->middleware('auth:sanctum');
    // Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    // Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout')->middleware('auth:sanctum');
    // Route::post('/otp', [AuthController::class, 'sendOtp'])->name('auth.sendOtp');
    // Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->name('auth.verifyOtp');

});

// Product Routes
Route::prefix('product')->group(function () {
    Route::get('/', [ProductController::class, 'show_all_product'])->name('product.index');
    Route::get('/{product}', [ProductController::class, 'get_product_data'])->name('product.show');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [ProductController::class, 'create_product'])->name('product.store');
        Route::put('/{product}', [ProductController::class, 'update_product_data'])->name('product.update');
        Route::delete('/{product}', [ProductController::class, 'remove_product'])->name('product.destroy');
    });
});