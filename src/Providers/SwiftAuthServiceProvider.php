<?php

namespace Teleurban\SwiftAuth\Providers;

use App\Providers\AppServiceProvider;
use Illuminate\Support\ServiceProvider;
use Teleurban\SwiftAuth\Console\Commands\ExampleCommand;

final class SwiftAuthServiceProvider extends AppServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/swift-auth.php', 'swift-auth');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'swift-auth');
        if ($this->app->runningInConsole()) {
            $this->commands(
                ExampleCommand::class
            );
        }
    }
}
