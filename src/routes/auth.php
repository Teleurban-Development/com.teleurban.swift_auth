<?php

use Illuminate\Support\Facades\Route;
use Teleurban\SwiftAuth\Http\Controllers\AuthController;

Route::middleware('web')->prefix('swift-auth')->as('swift-auth.')->group(function () {
    Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit');

    Route::get('register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('register', [AuthController::class, 'store'])->name('store');

    Route::match(['get', 'post'], 'logout', [AuthController::class, 'logout'])->name('logout');

    Route::prefix('password')->as('password.')->group(
        function () {
            Route::get('reset', [AuthController::class, 'showResetForm'])->name('request');
            Route::post('email', [AuthController::class, 'sendResetLink'])->name('email');

            Route::get('reset/{token}', [AuthController::class, 'showNewPasswordForm'])->name('reset');
            Route::post('reset', [AuthController::class, 'updatePassword'])->name('update');
        }
    );

    require __DIR__ . '/user.php';
});
