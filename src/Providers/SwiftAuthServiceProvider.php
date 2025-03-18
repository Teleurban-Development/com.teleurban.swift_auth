<?php

namespace Teleurban\SwiftAuth\Providers;

use Teleurban\SwiftAuth\Console\Commands\ExampleCommand;
use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;

final class SwiftAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/swift-auth.php', 'swift-auth');
    }

    public function boot(Router $router): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'swift-auth');

        $this->publishes(
            [
                __DIR__ . '/../resources/views' => resource_path('views/vendor/swift-auth'),
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

        if ($this->app->runningInConsole()) {
            $this->commands(
                ExampleCommand::class
            );
        }
    }
}
