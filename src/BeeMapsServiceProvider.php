<?php

namespace BeeDelivery\BeeMaps;

use Illuminate\Support\ServiceProvider;

final class BeeMapsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bee-maps.php', 'bee-maps');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/bee-maps.php' => config_path('bee-maps.php'),
            ], 'bee-maps-config');
        }
    }
}
