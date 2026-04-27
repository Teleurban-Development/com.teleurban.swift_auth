<?php

/**
 * Service provider that registers and bootstraps SwiftAuth components.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Providers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://github.com/EquidnaMX/swift_auth Package repository
 */

namespace Equidna\SwiftAuth\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Equidna\SwiftAuth\Classes\Auth\Events\MfaChallengeStarted;
use Equidna\SwiftAuth\Classes\Auth\Events\SessionEvicted;
use Equidna\SwiftAuth\Classes\Auth\Events\UserLoggedIn;
use Equidna\SwiftAuth\Classes\Auth\Events\UserLoggedOut;
use Equidna\SwiftAuth\Classes\Auth\Services\MfaService;
use Equidna\SwiftAuth\Classes\Auth\Services\RememberMeService;
use Equidna\SwiftAuth\Classes\Auth\Services\SessionManager;
use Equidna\SwiftAuth\Classes\Auth\Services\TokenMetadataValidator;
use Equidna\SwiftAuth\Classes\Auth\SwiftSessionAuth;
use Equidna\SwiftAuth\Classes\Users\Contracts\UserRepositoryInterface;
use Equidna\SwiftAuth\Classes\Users\Repositories\EloquentUserRepository;
use Equidna\SwiftAuth\Console\Commands\CreateAdminUser;
use Equidna\SwiftAuth\Console\Commands\InstallSwiftAuth;
use Equidna\SwiftAuth\Console\Commands\ListSessions;
use Equidna\SwiftAuth\Console\Commands\PreviewEmailTemplates;
use Equidna\SwiftAuth\Console\Commands\PurgeExpiredTokens;
use Equidna\SwiftAuth\Console\Commands\PurgeStaleSessions;
use Equidna\SwiftAuth\Console\Commands\RevokeUserSessions;
use Equidna\SwiftAuth\Console\Commands\UnlockUserCommand;
use Equidna\SwiftAuth\Http\Middleware\CanPerformAction;
use Equidna\SwiftAuth\Http\Middleware\RequireAuthentication;
use Equidna\SwiftAuth\Http\Middleware\SecurityHeaders;
use Equidna\SwiftAuth\Http\Middleware\ShareInertiaData;
use Equidna\SwiftAuth\Providers\RateLimitServiceProvider;

/**
 * Registers and bootstraps SwiftAuth components.
 *
 * Handles middleware registration, view/migration loading, config merging, command registration,
 * and publishes related assets.
 */
final class SwiftAuthServiceProvider extends ServiceProvider
{
    /**
     * Registers bindings in the container.
     *
     * Merges the package configuration and binds the 'swift-auth' service as a singleton.
     * Also binds the UserRepositoryInterface to its Eloquent implementation.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__ . '/../../config/swift-auth.php',
            key: 'swift-auth',
        );

        $this->app->singleton(
            abstract: UserRepositoryInterface::class,
            concrete: EloquentUserRepository::class,
        );

        $this->app->singleton(
            abstract: 'swift-auth',
            concrete: function ($app) {
                /** @var \Illuminate\Session\Store $sessionStore */
                $sessionStore = $app['session.store'];

                /** @var UserRepositoryInterface $userRepository */
                $userRepository = $app->make(UserRepositoryInterface::class);

                /** @var Dispatcher $dispatcher */
                $dispatcher = $app->make(Dispatcher::class);

                $metadataValidator = new TokenMetadataValidator();
                $rememberMeService = new RememberMeService(
                    userRepository: $userRepository,
                    metadataValidator: $metadataValidator,
                );

                $sessionManager = new SessionManager();
                $mfaService     = new MfaService(
                    session: $sessionStore,
                    events: $dispatcher,
                );

                return new SwiftSessionAuth(
                    session: $sessionStore,
                    userRepository: $userRepository,
                    events: $dispatcher,
                    rememberMeService: $rememberMeService,
                    sessionManager: $sessionManager,
                    mfaService: $mfaService,
                );
            },
        );
    }

    /**
     * Bootstraps any application services.
     *
     * Registers middleware aliases, loads routes/views/migrations, and publishes assets.
     * Validates configuration on boot to catch misconfigurations early.
     *
     * @param  Router $router  Laravel router instance.
     * @return void
     */
    public function boot(Router $router): void
    {
        $this->validateConfiguration();

        // Restore locale from session
        $locale = Session::get('locale', config('app.locale', 'en'));
        if (in_array($locale, ['en', 'es'], strict: true)) {
            App::setLocale($locale);
        }

        // Register rate limiting
        $this->app->register(RateLimitServiceProvider::class);

        $this->registerEventListeners();

        // Register middleware
        $router->aliasMiddleware('SwiftAuth.RequireAuthentication', RequireAuthentication::class);
        $router->aliasMiddleware('SwiftAuth.CanPerformAction', CanPerformAction::class);
        $router->aliasMiddleware('SwiftAuth.SecurityHeaders', SecurityHeaders::class);
        $router->aliasMiddleware('SwiftAuth.ShareInertiaData', ShareInertiaData::class);
        $router->aliasMiddleware('SwiftAuth.AuthenticateWithToken', \Equidna\SwiftAuth\Http\Middleware\AuthenticateWithToken::class);
        $router->aliasMiddleware('SwiftAuth.CheckTokenAbilities', \Equidna\SwiftAuth\Http\Middleware\CheckTokenAbilities::class);

        // Load package resources
        $this->loadRoutesFrom(__DIR__ . '/../../routes/swift-auth.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'swift-auth');
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'swift-auth');

        // Publish config
        $this->publishes(
            paths: [
                __DIR__ . '/../../config/swift-auth.php' => config_path('swift-auth.php'),
            ],
            groups: 'swift-auth:config',
        );

        // Publish application models
        $this->publishes(
            paths: [
                __DIR__ . '/../Models' => app_path('Models/SwiftAuth'),
            ],
            groups: 'swift-auth:models',
        );

        // Publish Blade views (into a vendor subfolder to avoid overwriting app views)
        $this->publishes(
            paths: [
                __DIR__ . '/../../resources/views' => resource_path('views/vendor/swift-auth'),
            ],
            groups: 'swift-auth:views',
        );

        // Publish translations
        $this->publishes(
            paths: [
                __DIR__ . '/../../resources/lang' => resource_path('lang/vendor/swift-auth'),
            ],
            groups: 'swift-auth:lang',
        );

        // Publish migrations
        $this->publishes(
            paths: [
                __DIR__ . '/../../database/migrations' => database_path('migrations/swift-auth'),
            ],
            groups: 'swift-auth:migrations',
        );

        // Publish React + TypeScript views
        $this->publishes(
            paths: [
                __DIR__ . '/../../resources/ts' => resource_path('ts/swift-auth'),
            ],
            groups: 'swift-auth:ts-react',
        );

        // Publish React + JavaScript views
        $this->publishes(
            paths: [
                __DIR__ . '/../../resources/js' => resource_path('js/swift-auth'),
            ],
            groups: 'swift-auth:js-react',
        );

        // Publish icons
        $this->publishes(
            paths: [
                __DIR__ . '/../../resources/icons' => public_path('icons/swift-auth'),
            ],
            groups: 'swift-auth:icons',
        );

        // Register Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateAdminUser::class,
                InstallSwiftAuth::class,
                ListSessions::class,
                PreviewEmailTemplates::class,
                PurgeExpiredTokens::class,
                PurgeStaleSessions::class,
                RevokeUserSessions::class,
                UnlockUserCommand::class,
            ]);

            $this->callAfterResolving(
                Schedule::class,
                function (Schedule $schedule): void {
                    $schedule->command(PurgeExpiredTokens::class)->hourly();

                    $cleanupEnabled = (bool) config('swift-auth.session_cleanup.enabled', true);
                    if ($cleanupEnabled) {
                        $frequency = (string) config('swift-auth.session_cleanup.schedule', 'daily');

                        $event = $schedule->command(PurgeStaleSessions::class);

                        if (method_exists($event, $frequency)) {
                            $event->{$frequency}();
                        } else {
                            $event->cron($frequency);
                        }
                    }
                },
            );
        }
    }

    /**
     * Validates swift-auth configuration and logs warnings for invalid values.
     *
     * @return void
     */
    private function validateConfiguration(): void
    {
        $maxAttempts = config('swift-auth.account_lockout.max_attempts', 5);
        $lockoutDuration = config('swift-auth.account_lockout.lockout_duration', 900);
        $passwordMinLength = config('swift-auth.password_min_length', 8);

        if ($maxAttempts < 1) {
            logger()->warning('swift-auth.config.invalid-max-attempts', [
                'value' => $maxAttempts,
                'default' => 5,
            ]);
        }

        if ($lockoutDuration < 60) {
            logger()->warning('swift-auth.config.invalid-lockout-duration', [
                'value' => $lockoutDuration,
                'minimum' => 60,
                'default' => 900,
            ]);
        }

        if ($passwordMinLength < 8 || $passwordMinLength > 128) {
            logger()->warning('swift-auth.config.invalid-password-min-length', [
                'value' => $passwordMinLength,
                'default' => 8,
            ]);
        }

        $hashDriver = config('swift-auth.hash_driver');
        if ($hashDriver !== null && !in_array($hashDriver, ['bcrypt', 'argon', 'argon2id'], true)) {
            logger()->warning('swift-auth.config.invalid-hash-driver', [
                'value' => $hashDriver,
                'supported' => ['bcrypt', 'argon', 'argon2id', 'null (default)'],
            ]);
        }

        // Validate session lifetimes
        $idleTimeout = (int) config('swift-auth.session_lifetimes.idle_timeout_seconds', 900);
        $absoluteTimeout = (int) config('swift-auth.session_lifetimes.absolute_timeout_seconds', 28800);

        if ($idleTimeout <= 0) {
            logger()->warning('swift-auth.config.invalid-idle-timeout', [
                'value' => $idleTimeout,
                'minimum' => 300,
                'default' => 900,
            ]);
        }

        if ($absoluteTimeout > 0 && $idleTimeout > 0 && $idleTimeout >= $absoluteTimeout) {
            logger()->warning('swift-auth.config.idle-timeout-exceeds-absolute', [
                'idle_timeout' => $idleTimeout,
                'absolute_timeout' => $absoluteTimeout,
            ]);
        }

        // Validate rate limiting
        $loginRateLimit = config('swift-auth.login_rate_limits', []);
        $emailAttempts = (int) ($loginRateLimit['email']['attempts'] ?? 3);
        $ipAttempts = (int) ($loginRateLimit['ip']['attempts'] ?? 10);

        if ($emailAttempts < 1) {
            logger()->warning('swift-auth.config.invalid-login-email-attempts', [
                'value' => $emailAttempts,
                'minimum' => 1,
                'default' => 3,
            ]);
        }

        if ($ipAttempts < 1) {
            logger()->warning('swift-auth.config.invalid-login-ip-attempts', [
                'value' => $ipAttempts,
                'minimum' => 1,
                'default' => 10,
            ]);
        }

        // Validate MFA configuration
        $mfaEnabled = config('swift-auth.mfa.enabled', false);
        if ($mfaEnabled) {
            $mfaDriver = config('swift-auth.mfa.driver', 'otp');
            if (!in_array($mfaDriver, ['otp', 'webauthn'], true)) {
                logger()->warning('swift-auth.config.invalid-mfa-driver', [
                    'value' => $mfaDriver,
                    'supported' => ['otp', 'webauthn'],
                    'default' => 'otp',
                ]);
            }
        }

        // Validate remember-me configuration
        $rememberEnabled = config('swift-auth.remember_me.enabled', true);
        if ($rememberEnabled) {
            $rememberTtl = (int) config('swift-auth.remember_me.ttl_seconds', 1209600);
            if ($rememberTtl <= 0) {
                logger()->warning('swift-auth.config.invalid-remember-ttl', [
                    'value' => $rememberTtl,
                    'minimum' => 3600,
                    'default' => 1209600,
                ]);
            }

            $policy = config('swift-auth.remember_me.policy', 'strict');
            if (!in_array($policy, ['strict', 'lenient'], true)) {
                logger()->warning('swift-auth.config.invalid-remember-policy', [
                    'value' => $policy,
                    'supported' => ['strict', 'lenient'],
                    'default' => 'strict',
                ]);
            }
        }
    }

    /**
     * Registers listeners for SwiftAuth events.
     *
     * Applications can override these listeners by registering their own listeners
     * for the same event classes.
     *
     * @return void
     */
    private function registerEventListeners(): void
    {
        $dispatcher = $this->app->make(Dispatcher::class);

        $dispatcher->listen(UserLoggedIn::class, function (UserLoggedIn $event): void {
            logger()->info('swift-auth.user.logged-in', [
                'user_id' => $event->userId,
                'session_id' => $event->sessionId,
                'ip' => $event->ipAddress,
                'driver' => $event->driverMetadata,
            ]);
        });

        $dispatcher->listen(UserLoggedOut::class, function (UserLoggedOut $event): void {
            logger()->info('swift-auth.user.logged-out', [
                'user_id' => $event->userId,
                'session_id' => $event->sessionId,
                'ip' => $event->ipAddress,
                'driver' => $event->driverMetadata,
            ]);
        });

        $dispatcher->listen(SessionEvicted::class, function (SessionEvicted $event): void {
            logger()->info('swift-auth.session.evicted', [
                'user_id' => $event->userId,
                'session_id' => $event->sessionId,
                'ip' => $event->ipAddress,
                'driver' => $event->driverMetadata,
            ]);
        });

        $dispatcher->listen(MfaChallengeStarted::class, function (MfaChallengeStarted $event): void {
            logger()->info('swift-auth.mfa.challenge-started', [
                'user_id' => $event->userId,
                'session_id' => $event->sessionId,
                'ip' => $event->ipAddress,
                'driver' => $event->driverMetadata,
            ]);
        });
    }
}
