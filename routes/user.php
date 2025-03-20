<?php

use Teleurban\SwiftAuth\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('SwiftAuthMiddleware')->prefix('users')->as('user.')->group(
    function () {
        Route::get('', [AuthController::class, 'index'])->name('index');
        Route::post('', [AuthController::class, 'store'])->name('store');
        Route::get('create', [AuthController::class, 'showNewUserForm'])->name('create');
        
        Route::prefix('{id}')->group(
            function () {
                Route::get('', [AuthController::class, 'show'])->name('show');
                Route::get('edit', [AuthController::class, 'showEditUserForm'])->name('edit');

                Route::middleware('SwiftAuthMiddleware' . ':root')->group(function () { // TODO: ([AuthenticateUser::class . ':root'])
                    Route::put('', [AuthController::class, 'update'])->name('update');
                    Route::delete('', [AuthController::class, 'destroy'])->name('destroy');
                });
            }
        );

        require __DIR__ . '/roles.php';
    }
);
