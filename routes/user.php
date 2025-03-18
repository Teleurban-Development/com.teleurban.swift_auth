<?php

use Teleurban\SwiftAuth\Middleware\AuthenticateUsers;
use Teleurban\SwiftAuth\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware(AuthenticateUsers::class)->prefix('users')->as('user.')->group(
    function () {
        Route::get('', [AuthController::class, 'index'])->name('index');

        Route::prefix('{id}')->group(
            function () {
                Route::get('', [AuthController::class, 'show'])->name('show');

                Route::middleware(AuthenticateUsers::class . ':root')->group(function () {
                    Route::put('', [AuthController::class, 'update'])->name('update');
                    Route::delete('', [AuthController::class, 'destroy'])->name('destroy');
                });
            }
        );
    }
);
