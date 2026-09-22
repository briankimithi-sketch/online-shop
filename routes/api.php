<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Flutterwave Callbacks (Public - No Auth Required)
|--------------------------------------------------------------------------
|
| These routes are called by Flutterwave after payment completion.
| They don't require authentication as Flutterwave calls them directly.
|
*/

Route::get('/payments/flutterwave/callback', [PaymentController::class, 'flutterwaveCallback'])
    ->name('payments.flutterwave.callback');

Route::get('/payments/flutterwave/success', [PaymentController::class, 'flutterwaveSuccess'])
    ->name('payments.flutterwave.success');

Route::get('/payments/flutterwave/cancel', [PaymentController::class, 'flutterwaveCancel'])
    ->name('payments.flutterwave.cancel');

Route::post('/payments/flutterwave/webhook', [PaymentController::class, 'flutterwaveWebhook'])
    ->name('payments.flutterwave.webhook');

/*
|--------------------------------------------------------------------------
| M-Pesa Callbacks (Public - No Auth Required)
|--------------------------------------------------------------------------
|
| These routes are called by Safaricom Daraja after payment completion.
| They don't require authentication as Safaricom calls them directly.
|
*/

Route::post('/payments/mpesa/callback', [PaymentController::class, 'mpesaCallback'])
    ->name('payments.mpesa.callback');

Route::post('/payments/mpesa/validation', [PaymentController::class, 'mpesaValidation'])
    ->name('payments.mpesa.validation');

Route::post('/payments/mpesa/confirmation', [PaymentController::class, 'mpesaConfirmation'])
    ->name('payments.mpesa.confirmation');

/*
|--------------------------------------------------------------------------
| Public Authentication Routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register'])
    ->name('register');

Route::post('/login', [AuthController::class, 'login'])
    ->name('login');

/*
|--------------------------------------------------------------------------
| Public Product Routes
|--------------------------------------------------------------------------
|
| Anyone can view products without logging in.
|
*/

Route::get('/products', [ProductController::class, 'index'])
    ->name('products.index');

Route::get('/products/{product}', [ProductController::class, 'show'])
    ->name('products.show');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
|
| These routes require a valid Sanctum authentication token.
|
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */

    Route::resource('users', UserController::class);

    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */

    Route::post('/products', [ProductController::class, 'store'])
        ->name('products.store');

    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->name('products.update');

    Route::patch('/products/{product}', [ProductController::class, 'update'])
        ->name('products.update.patch');

    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->name('products.destroy');

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */

    Route::resource('orders', OrderController::class);

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    Route::resource('payments', PaymentController::class);

    /*
    |--------------------------------------------------------------------------
    | Flutterwave Payment
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/payments/{payment}/flutterwave/initiate',
        [PaymentController::class, 'flutterwaveInitiate']
    )->name('payments.flutterwave.initiate');

    Route::get(
        '/payments/{payment}/flutterwave/inline-data',
        [PaymentController::class, 'flutterwaveInlineData']
    )->name('payments.flutterwave.inline-data');

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Payment
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/payments/{payment}/mpesa/stk-push',
        [PaymentController::class, 'mpesaStkPush']
    )->name('payments.mpesa.stk-push');

    Route::get(
        '/payments/{payment}/mpesa/query',
        [PaymentController::class, 'mpesaQuery']
    )->name('payments.mpesa.query');

    Route::get(
        '/payments/{payment}/mpesa/inline-data',
        [PaymentController::class, 'mpesaInlineData']
    )->name('payments.mpesa.inline-data');

    /*
    |--------------------------------------------------------------------------
    | Demo Payment
    |--------------------------------------------------------------------------
    |
    | Simulates a successful payment without a real payment gateway.
    |
    |
    */

    Route::post(
        '/payments/{payment}/demo-pay',
        [PaymentController::class, 'demoPay']
    )->name('payments.demo-pay');

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    Route::resource('roles', RoleController::class);
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Every route in this group requires:
|
| 1. A valid Sanctum token
| 2. role_id = 1
|
*/

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Admin Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get('/dashboard', [
            AdminDashboardController::class,
            'index',
        ])->name('admin.dashboard');
    });
