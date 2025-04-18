<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CartController;
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

// Category Routes
Route::prefix('category')->group(function () {
    Route::post('/', [CategoryController::class, 'create_category'])->name('category.create')->middleware('auth:sanctum');
    Route::get('/', [CategoryController::class, 'get_all_categories'])->name('category.all');
    Route::get('/{id}', [CategoryController::class, 'get_one_category'])->name('category.single')->middleware('auth:sanctum');
    Route::put('/{id}', [CategoryController::class, 'update_category'])->name('category.update')->middleware('auth:sanctum');
    Route::delete('/{id}', [CategoryController::class, 'delete_category'])->name('category.delete')->middleware('auth:sanctum');
});

// Product Routes
Route::prefix('product')->group(function () {
    Route::get('/', [ProductController::class, 'show_all_product'])->name('product.all');
    Route::get('/{id}', [ProductController::class, 'get_product_data'])->name('product.single');
    Route::post('/', [ProductController::class, 'create_product'])->name('product.create')->middleware('auth:sanctum');
    Route::put('/{id}', [ProductController::class, 'update_product_data'])->name('product.update')->middleware('auth:sanctum');
    Route::delete('/{id}', [ProductController::class, 'remove_product'])->name('product.delete')->middleware('auth:sanctum');
});

// Cart Routes
Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('cart.index');
    Route::post('/{id_user}/product/{id_product}', [CartController::class, 'add_item_to_cart'])->name('cart.add_item');
});

