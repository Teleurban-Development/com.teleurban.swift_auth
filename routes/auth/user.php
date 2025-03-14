<?php

use App\Http\Controllers\UserController;
use App\Http\Middleware\AuthenticateUsers;
use Illuminate\Support\Facades\Route;


Route::middleware(AuthenticateUsers::class)->prefix('users')->as('user.')->group(
    function () {
        Route::get('', [UserController::class, 'index'])->name('index');

        Route::post('', [UserController::class, 'store'])->name('store')
            ->withoutMiddleware(AuthenticateUsers::class);

        Route::prefix('{id}')->group(
            function () {
                Route::get('', [UserController::class, 'show'])->name('show');
                Route::put('', [UserController::class, 'update'])->name('update');
                Route::delete('', [UserController::class, 'destroy'])->name('destroy');
            }
        );
    }
);
