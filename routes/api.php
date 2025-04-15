<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CategoryController;
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
Route::prefix('auth')->middleware('api')->group(function () {
    Route::post('/register/penitip', [AuthController::class, 'register_penitip'])->name('auth.register.penitip');
    Route::post('/register/pengguna', [AuthController::class, 'register_pengguna'])->name('auth.register.pengguna');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout')->middleware('auth:sanctum');
    Route::post('/otp', [AuthController::class, 'sendOtp'])->name('auth.sendOtp');
    Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->name('auth.verifyOtp');
    Route::get('/otp/verify-page', [AuthController::class, 'verifyOtpPage'])->name('auth.verifyOtpPage');
    // New route for verification link
    Route::get('/verify/{token}', [AuthController::class, 'verifyEmail'])->name('auth.verifyEmail');
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