<?php

use Teleurban\SwiftAuth\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->as('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit');

    Route::get('register', [AuthController::class, 'showRegisterForm'])->name('register');

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
