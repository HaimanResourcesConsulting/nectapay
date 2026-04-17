<?php

namespace HRC\NectaPay\Laravel;

use HRC\NectaPay\Config;
use HRC\NectaPay\Contracts\CacheInterface;
use HRC\NectaPay\Contracts\HttpClientInterface;
use HRC\NectaPay\NectaPayClient;
use HRC\NectaPay\Laravel\Cache\LaravelCacheAdapter;
use HRC\NectaPay\Laravel\Console\ProvisionVirtualAccountsCommand;
use HRC\NectaPay\Laravel\Http\LaravelHttpClient;
use Illuminate\Support\ServiceProvider;

class NectaPayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/nectapay.php', 'nectapay');

        $this->app->singleton(CacheInterface::class, LaravelCacheAdapter::class);
        $this->app->singleton(HttpClientInterface::class, LaravelHttpClient::class);

        $this->app->singleton(Config::class, function () {
            return Config::fromArray(config('nectapay'));
        });

        $this->app->singleton(NectaPayClient::class, function ($app) {
            return new NectaPayClient(
                config: $app->make(Config::class),
                httpClient: $app->make(HttpClientInterface::class),
                cache: $app->make(CacheInterface::class),
            );
        });

        $this->app->singleton(NectaPayService::class, function ($app) {
            return new NectaPayService($app->make(NectaPayClient::class));
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/nectapay.php' => config_path('nectapay.php'),
        ], 'nectapay-config');

        $this->publishes([
            __DIR__ . '/../../database/migrations/' => database_path('migrations'),
        ], 'nectapay-migrations');

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/webhook.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProvisionVirtualAccountsCommand::class,
            ]);
        }
    }
}
