<?php

namespace Teleurban\SwiftAuth;

use Teleurban\SwiftAuth\Console\Commands\ExampleCommand;
use Illuminate\Support\ServiceProvider;

final class SwiftAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/swift-auth.php', 'swift-auth');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'swift-auth');

        $this->publishes(
            [
                __DIR__ . '/../../resources/views' => resource_path('views/swift-auth'),
            ],
            [
                'swift-auth:views',
                'swift-auth',
            ]
        );

        $this->loadMigrationsFrom(__DIR__ . '/../migrations');

        $this->publishes(
            [
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ],
            [
                'swift-auth:migrations',
                'swift-auth'
            ]
        );

        if ($this->app->runningInConsole()) {
            $this->commands(
                ExampleCommand::class
            );
        }
    }
}
