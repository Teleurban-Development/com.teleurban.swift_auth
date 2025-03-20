<?php

namespace Teleurban\SwiftAuth\Providers;

use Teleurban\SwiftAuth\Console\Commands\ExampleCommand;
use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Teleurban\SwiftAuth\Console\Commands\InstallSwiftAuth;

final class SwiftAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/swift-auth.php', 'swift-auth');
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('SwiftAuthMiddleware', \Teleurban\SwiftAuth\Middleware\AuthenticatedUser::class);

        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'swift-auth');

        $this->publishes(
            [
                __DIR__ . '/../../resources/views' => resource_path('views'),
            ],
            [
                'swift-auth:views'
            ]
        );

        $this->loadMigrationsFrom(__DIR__ . '/../../migrations');

        $this->publishes(
            [
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ],
            [
                'swift-auth:migrations'
            ]
        );

        $this->publishes(
            [
                __DIR__ . '/../../resources/ts' => resource_path('js'),
            ],
            ['swift-auth:ts-react']
        );

        $this->publishes(
            [
                __DIR__ . '/../../resources/js' => resource_path('js'),
            ],
            ['swift-auth:js-react']
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExampleCommand::class,
                InstallSwiftAuth::class,
            ]);
        }
        
    }
}
