<?php

namespace BeeDelivery\BeeMaps;

use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use Illuminate\Support\ServiceProvider;

final class BeeMapsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bee-maps.php', 'bee-maps');

        $this->app->singleton(MapsHttpClient::class, fn ($app) => new MapsHttpClient(
            $app->make(\Illuminate\Http\Client\Factory::class),
            $app->make(\Illuminate\Contracts\Events\Dispatcher::class),
            $app['config']->get('bee-maps.http'),
        ));
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
