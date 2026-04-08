<?php

namespace Logcutter\LogPulse;

use Illuminate\Support\ServiceProvider;

class LogPulseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/logpulse.php', 'logpulse');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'logpulse');

        $this->publishes([
            __DIR__.'/../config/logpulse.php' => config_path('logpulse.php'),
        ], 'logpulse-config');

        $this->publishes([
            __DIR__.'/../resources/js/pages/logpulse/index.tsx' => resource_path('js/pages/logpulse/index.tsx'),
        ], 'logpulse-inertia-page');
    }
}
