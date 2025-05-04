<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ConfigController;
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
    // Route::post('/otp', [AuthController::class, 'sendOtp'])->name('auth.sendOtp');
    // Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->name('auth.verifyOtp');
    // Route::get('/otp/verify-page', [AuthController::class, 'verifyOtpPage'])->name('auth.verifyOtpPage');
    Route::get('/verify/{token}', [AuthController::class, 'verifyEmail'])->name('auth.verify');
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

// Cart Routes (Authenticated Users Only)
Route::prefix('cart')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('cart.index');
    Route::get('/{id_user}', [CartController::class, 'show'])->name('cart.show');
    Route::post('/{id_user}/product/{id_product}', [CartController::class, 'add_item_to_cart'])->name('cart.add_item');
    Route::put('/{id_user}/product/{id_product}', [CartController::class, 'update'])->name('cart.update_item');
    Route::delete('/{id_user}/product/{id_product}', [CartController::class, 'destroy'])->name('cart.remove_item');
});

// Cart Routes (Guest Users)
Route::prefix('guest/cart')->group(function () {
    Route::get('/{guest_id}', [CartController::class, 'guest_show'])->name('cart.guest.show');
    Route::post('/{guest_id}/product/{id_product}', [CartController::class, 'guest_add_item'])->name('cart.guest.add_item');
    Route::put('/{guest_id}/product/{id_product}', [CartController::class, 'guest_update_item'])->name('cart.guest.update_item');
    Route::delete('/{guest_id}/product/{id_product}', [CartController::class, 'guest_remove_item'])->name('cart.guest.remove_item');
});

Route::prefix('config')->group(function () {
    Route::get('/', [ConfigController::class, 'index'])->name('config.index')->middleware('auth:sanctum');
    Route::get('/{id}', [ConfigController::class, 'show'])->name('config.show')->middleware('auth:sanctum');
    Route::post('/', [ConfigController::class, 'create'])->name('config.create')->middleware('auth:sanctum');
    Route::put('/{id}', [ConfigController::class, 'update'])->name('config.update')->middleware('auth:sanctum');
    Route::delete('/{id}', [ConfigController::class, 'delete'])->name('config.delete')->middleware('auth:sanctum');
});