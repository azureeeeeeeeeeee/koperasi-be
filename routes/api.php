<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PaymentGatewayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmailController;
use App\Models\User;


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
    Route::put('/password', [AuthController::class, 'changePassword'])->name('auth.change.password')->middleware('auth:sanctum');
    Route::put('/user', [AuthController::class, 'update_pengguna'])->name('auth.update.user')->middleware('auth:sanctum');
});

// Admin Routes
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/user', [AdminController::class, 'create_pengguna'])->name('admin.create.pengguna');
    Route::delete('/user/{id}', [AdminController::class, 'delete_pengguna'])->name('admin.delete.pengguna');
    Route::get('/user', [AdminController::class, 'get_pengguna'])->name('admin.get.pengguna');
    Route::get('/user/{id}', [AdminController::class, 'get_pengguna_by_id'])->name('admin.get.pengguna.by.id');
    Route::put('/user/{id}', [AdminController::class, 'update_pengguna'])->name('admin.update.pengguna');
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
    Route::get('/{id}', [ProductController::class, 'get_product_data'])->name('product.single')->whereNumber('id');
    Route::get('/search', [ProductController::class, 'index'])->name('product.index');
    Route::post('/', [ProductController::class, 'create_product'])->name('product.create')->middleware('auth:sanctum');
    Route::put('/{id}', [ProductController::class, 'update_product_data'])->name('product.update')->middleware('auth:sanctum');
    Route::delete('/{id}', [ProductController::class, 'remove_product'])->name('product.delete')->middleware('auth:sanctum');
});

// Cart Routes (Authenticated Users Only)
Route::prefix('cart')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('cart.index');
    Route::get('/{id_user}', [CartController::class, 'show'])->name('cart.show');
    Route::get('/{id_user}/history', [CartController::class, 'showByUser'])->name('cart.show.user');
    Route::post('/{id_user}/product/{id_product}', [CartController::class, 'add_item_to_cart'])->name('cart.add_item');
    Route::put('/{id_user}/product/{id_product}', [CartController::class, 'update'])->name('cart.update_item');
    Route::delete('/{id_user}/product/{id_product}', [CartController::class, 'destroy'])->name('cart.remove_item');
    Route::put('/{id_user}/status', [CartController::class, 'update_status_barang'])->name('cart.update_status');
});

// Cart Routes (Guest Users)
Route::prefix('guest/cart')->group(function () {
    Route::get('/{guest_id}', [CartController::class, 'guest_show'])->name('cart.guest.show');
    Route::post('/{guest_id}/product/{id_product}', [CartController::class, 'guest_add_item'])->name('cart.guest.add_item');
    Route::put('/{guest_id}/product/{id_product}', [CartController::class, 'guest_update_item'])->name('cart.guest.update_item');
    Route::delete('/{guest_id}/product/{id_product}', [CartController::class, 'guest_remove_item'])->name('cart.guest.remove_item');
    Route::put('/{guest_id}/status', [CartController::class, 'guest_update_status'])->name('cart.guest_update_status');
});

// Payment Gateway Routes
Route::prefix('payment')->group(function () {
    Route::post('/create-payment', [PaymentGatewayController::class, 'createPayment'])->name('payment.create');
    Route::get('/check-payment-status', [PaymentGatewayController::class, 'checkPaymentStatus'])->name('payment.status');
    Route::post('/pay-for-cart', [PaymentGatewayController::class, 'payForCart'])->name('payment.cart_payment');
    Route::post('/pay-for-membership', [PaymentGatewayController::class, 'payForMembership'])->name('payment.payForMembership');
    Route::post('/pay-for-topup', [PaymentGatewayController::class, 'topup'])->name('payment.topup');
    Route::get('/transaction', [PaymentGatewayController::class, 'getTransactions'])->name('payment.transaction')->middleware('auth:sanctum');
    Route::post('/withdrawl-cash', [PaymentGatewayController::class, 'withdrawlCash'])->name('payment.withdrawl');
});

// Email Routes
Route::prefix('email')->group(function () {
    Route::post('/send-otp', [EmailController::class, 'sendOtp'])->name('email.sendOtp');
    Route::post('/verify-otp', [EmailController::class, 'verifyOtp'])->name('email.verifyOtp');
    Route::post('/reset-password', [EmailController::class, 'resetPasswordWithOtp'])->name('email.resetPasswordWithOtp');
});

// Config Routes
Route::prefix('config')->group(function () {
    Route::get('/', [ConfigController::class, 'index'])->name('config.index')->middleware('auth:sanctum');
    Route::get('/{id}', [ConfigController::class, 'show'])->name('config.show')->middleware('auth:sanctum');
    Route::post('/', [ConfigController::class, 'create'])->name('config.create')->middleware('auth:sanctum');
    Route::put('/{id}', [ConfigController::class, 'update'])->name('config.update')->middleware('auth:sanctum');
    Route::delete('/{id}', [ConfigController::class, 'delete'])->name('config.delete')->middleware('auth:sanctum');
});


Route::put('/admin/user/membership/reset', function (Request $request) {
    $user = $request->user();

    if (!$user || $user->tipe !== 'admin') {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak. Hanya admin yang dapat mengakses endpoint ini.',
        ], 403);
    }

    $users = User::whereIn('tipe', ['penitip', 'pengguna'])->get();

    if ($users->isEmpty()) {
        return response()->json([
            'message' => 'Tidak ada user dengan tipe penitip atau pengguna',
        ], 404);
    }

    foreach ($users as $u) {
        $u->update(['status_keanggotaan' => 'tidak aktif']);
    }

    return response()->json([
        'message' => 'Status keanggotaan semua user dengan tipe penitip dan pengguna telah diubah menjadi tidak aktif',
    ], 200);
})->middleware('auth:sanctum');