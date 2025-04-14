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
Route::prefix('auth')->group(function () {
    Route::post('/register/penitip', [AuthController::class, 'register_penitip'])->name('auth.register.penitip');
    Route::post('/register/pengguna', [AuthController::class, 'register_pengguna'])->name('auth.register.pengguna');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout')->middleware('auth:sanctum');
    Route::post('/otp', [AuthController::class, 'sendOtp'])->name('auth.sendOtp');
    Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->name('auth.verifyOtp');

});

// Admin Routes
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/user', [AdminController::class, 'create_pengguna'])->name('admin.create.pengguna');
    Route::delete('/user/{id}', [AdminController::class, 'delete_pengguna'])->name('admin.delete.pengguna');
    Route::get('/user', [AdminController::class, 'get_pengguna'])->name('admin.get.pengguna');
    Route::get('/user/{id}', [AdminController::class, 'get_pengguna_by_id'])->name('admin.get.pengguna.by.id');
    Route::put('/user/{id}', [AdminController::class, 'update_pengguna'])->name('admin.update.pengguna');
});