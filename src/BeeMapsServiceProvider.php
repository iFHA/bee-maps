<?php

namespace BeeDelivery\BeeMaps;

use BeeDelivery\BeeMaps\Providers\Google\GoogleProvider;
use BeeDelivery\BeeMaps\Providers\Here\HereProvider;
use BeeDelivery\BeeMaps\Providers\ProviderRegistry;
use BeeDelivery\BeeMaps\Support\ConfigMerge;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

final class BeeMapsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->deepMergeConfig(__DIR__ . '/../config/bee-maps.php', 'bee-maps');

        $this->app->singleton(MapsHttpClient::class, fn ($app) => new MapsHttpClient(
            $app->make(Factory::class),
            $app->make(Dispatcher::class),
            $app['config']->get('bee-maps.http'),
        ));

        $this->app->singleton(GoogleProvider::class, fn ($app) => new GoogleProvider(
            $app->make(MapsHttpClient::class),
            $app['config']->get('bee-maps.google'),
            $app['config']->get('bee-maps.defaults.language'),
            $app['config']->get('bee-maps.defaults.region'),
        ));

        $this->app->singleton(HereProvider::class, fn ($app) => new HereProvider(
            $app->make(MapsHttpClient::class),
            $app['config']->get('bee-maps.here'),
            $app['config']->get('bee-maps.defaults.language'),
            $app['config']->get('bee-maps.defaults.region'),
        ));

        $this->app->singleton(ProviderRegistry::class, fn ($app) => new ProviderRegistry(
            array_map(fn (string $class) => $app->make($class), $app['config']->get('bee-maps.providers', [])),
        ));

        $this->app->singleton(MapServiceFactory::class, fn ($app) => new MapServiceFactory(
            $app->make(ProviderRegistry::class),
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

    /**
     * Equivalente ao mergeConfigFrom, mas recursivo — ver ConfigMerge.
     *
     * A checagem de config cacheado e a mesma do Laravel, e nao e cosmetica: com
     * `php artisan config:cache` o .env nao e carregado, entao dar require no
     * arquivo reavaliaria todo env() dele e preencheria com null as chaves que o
     * operador definiu — trocando "Undefined array key" por credencial nula.
     */
    private function deepMergeConfig(string $path, string $key): void
    {
        if ($this->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');

        $config->set($key, ConfigMerge::deep(require $path, $config->get($key, [])));
    }

    private function configurationIsCached(): bool
    {
        return $this->app instanceof CachesConfiguration && $this->app->configurationIsCached();
    }
}
