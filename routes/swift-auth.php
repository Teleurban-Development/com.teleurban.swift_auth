<?php

/**
 * Routes for SwiftAuth package.
 *
 * PHP 8.1+
 *
 * @package Equidna\SwiftAuth\Routes
 */

use Illuminate\Support\Facades\Route;
use Equidna\SwiftAuth\Http\Controllers\AuthController;
use Equidna\SwiftAuth\Http\Controllers\EmailVerificationController;
use Equidna\SwiftAuth\Http\Controllers\LocaleController;
use Equidna\SwiftAuth\Http\Controllers\MfaController;
use Equidna\SwiftAuth\Http\Controllers\PasswordController;
use Equidna\SwiftAuth\Http\Controllers\UserController;
use Equidna\SwiftAuth\Http\Controllers\WebAuthnController;
use Equidna\SwiftAuth\Http\Middleware\CanPerformAction;
use Equidna\SwiftAuth\Http\Middleware\RequireAuthentication;

$routePrefix = (string) config('swift-auth.route_prefix', 'swift-auth');

Route::middleware(['web', 'SwiftAuth.SecurityHeaders'])
    ->prefix($routePrefix)
    ->as($routePrefix . '.')
    ->group(
        function () {
            // Locale switching
            Route::post('locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

            Route::get('login', [AuthController::class, 'showLoginForm'])->name('login.form');
            Route::post('login', [AuthController::class, 'login'])->name('login');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');

            // Password reset routes
            Route::prefix('password')->as('password.')
                ->group(
                    function () {
                        Route::get('', [PasswordController::class, 'showRequestForm'])->name('request.form');
                        Route::post('', [PasswordController::class, 'sendResetLink'])
                            ->middleware('throttle:swift-auth-password-reset')
                            ->name('request.send');

                        Route::get('sent', [PasswordController::class, 'showRequestSent'])->name('request.sent');
                        Route::get('{token}', [PasswordController::class, 'showResetForm'])->name('reset.form');
                        Route::post('reset', [PasswordController::class, 'resetPassword'])->name('reset.update');
                    }
                );

            // Multi-Factor Authentication (MFA) routes
            Route::prefix('mfa')->as('mfa.')
                ->group(
                    function () {
                        Route::post('otp/verify', [MfaController::class, 'verifyOtp'])->name('otp.verify');
                        Route::post('webauthn/verify', [MfaController::class, 'verifyWebAuthn'])
                            ->name('webauthn.verify');
                    }
                );

            // WebAuthn / Passkeys (Primary Auth & Registration)
            Route::prefix('webauthn')->as('webauthn.')
                ->group(function () {
                    // Registration (Requires Auth)
                    Route::middleware(RequireAuthentication::class)
                        ->group(function () {
                            Route::post('register/options', [WebAuthnController::class, 'registerOptions'])
                                ->name('register.options');
                            Route::post('register', [WebAuthnController::class, 'register'])
                                ->name('register');
                        });

                    // Login (Public)
                    Route::post('login/options', [WebAuthnController::class, 'loginOptions'])
                        ->name('login.options');
                    Route::post('login', [WebAuthnController::class, 'login'])
                        ->name('login');
                });

            // Email verification routes
            Route::prefix('email')->as('email.')
                ->group(
                    function () {
                        Route::post('send', [EmailVerificationController::class, 'send'])
                            ->name('send');
                        Route::get('verify/{token}', [EmailVerificationController::class, 'verify'])
                            ->name('verify');
                    }
                );

            // Public registration routes (optional)
            if (config('swift-auth.allow_registration', false)) {
                Route::get('users/register', [UserController::class, 'register'])
                    ->name('public.register');

                Route::post('users', [UserController::class, 'store'])
                    ->middleware('throttle:swift-auth-registration')
                    ->name('public.register.store');
            }

            Route::middleware([
                RequireAuthentication::class,
            ])->group(
                function () {
                    require __DIR__ . '/swift-auth-sessions.php';

                    Route::middleware(CanPerformAction::class . ':sw-admin')
                        ->group(
                            function () {
                                require __DIR__ . '/swift-auth-users.php';
                                require __DIR__ . '/swift-auth-roles.php';
                                require __DIR__ . '/swift-auth-admin-sessions.php';
                            }
                        );
                }
            );
        }
    );
