<?php

namespace Teleurban\SwiftAuth\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Teleurban\SwiftAuth\Console\Commands\InstallSwiftAuth;
use Teleurban\SwiftAuth\Http\Middleware\AuthenticatedUser;

final class SwiftAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/swift-auth.php', 'swift-auth');
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('SwiftAuthMiddleware', AuthenticatedUser::class);

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'swift-auth');

        $this->publishes(
            [
                __DIR__ . '/../resources/views' => resource_path('views'),
            ],
            [
                'swift-auth:views'
            ]
        );

        $this->loadMigrationsFrom(__DIR__ . '/../migrations');
        $this->publishes(
            [
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ],
            [
                'swift-auth:migrations'
            ]
        );

        $this->publishes(
            [
                __DIR__ . '/../resources/ts' => resource_path('js'),
            ],
            ['swift-auth:ts-react']
        );

        $this->publishes(
            [
                __DIR__ . '/../resources/js' => resource_path('js'),
            ],
            ['swift-auth:js-react']
        );

        $this->publishes(
            [
                __DIR__ . '/../resources/icons' => public_path('icons'),
            ],
            ['swift-auth:icons']
        );

        if ($this->app->runningInConsole()) {
            $this->commands(
                [
                    InstallSwiftAuth::class,
                ]
            );
        }
    }
}
