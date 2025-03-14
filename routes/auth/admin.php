<?php

use App\Http\Controllers\AdminUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->as('admin.')->group(function () {
    Route::get('login', [AdminUserController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AdminUserController::class, 'login'])->name('login.submit');

    Route::get('register', [AdminUserController::class, 'showRegisterForm'])->name('register');

    Route::match(['get', 'post'], 'logout', [AdminUserController::class, 'logout'])->name('logout');

    Route::prefix('password')->as('password.')->group(
        function () {
            Route::get('reset', [AdminUserController::class, 'showResetForm'])->name('request');
            Route::post('email', [AdminUserController::class, 'sendResetLink'])->name('email');

            Route::get('reset/{token}', [AdminUserController::class, 'showNewPasswordForm'])->name('reset');
            Route::post('reset', [AdminUserController::class, 'updatePassword'])->name('update');
        }
    );

    require __DIR__ . '/user.php';
});
