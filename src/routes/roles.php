<?php

use Illuminate\Support\Facades\Route;
use Teleurban\SwiftAuth\Http\Controllers\RoleController;

Route::prefix('roles')->as('role.')->group(function () {
    Route::get('', [RoleController::class, 'index'])->name('index');
    Route::get('create', [RoleController::class, 'create'])->name('create');
    Route::get('assign', [RoleController::class, 'assignUserForm'])->name('assignForm');
    Route::get('{id}', [RoleController::class, 'show'])->name('show');

    Route::middleware('SwiftAuthMiddleware' . ':root')->group(function () {
        // TODO: ([AuthenticateUser::class . ':root'])
        Route::post('create', [RoleController::class, 'store'])->name('store');
        Route::post('assign', [RoleController::class, 'assignUser'])->name('assign');

        Route::put('{id}', [RoleController::class, 'update'])->name('update');
        Route::delete('{id}', [RoleController::class, 'destroy'])->name('destroy');
    });
});
