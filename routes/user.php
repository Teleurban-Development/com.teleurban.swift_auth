<?php

use Teleurban\SwiftAuth\Http\Middleware\AuthenticateUsers;
use Teleurban\SwiftAuth\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware(AuthenticateUsers::class)->prefix('users')->as('user.')->group(
    function () {
        Route::get('', [AuthController::class, 'index'])->name('index');

        Route::prefix('{id}')->group(
            function () {
                Route::get('', [AuthController::class, 'show'])->name('show');
                Route::put('', [AuthController::class, 'update'])->name('update');
                Route::delete('', [AuthController::class, 'destroy'])->name('destroy');
            }
        );
    }
);
