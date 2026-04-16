<?php

namespace HRC\NectaPay;

use HRC\NectaPay\Console\ProvisionVirtualAccountsCommand;
use HRC\NectaPay\Services\NectaPayService;
use Illuminate\Support\ServiceProvider;

class NectaPayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/nectapay.php', 'nectapay');

        $this->app->singleton(NectaPayService::class);
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/nectapay.php' => config_path('nectapay.php'),
        ], 'nectapay-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'nectapay-migrations');

        // Load migrations from the package
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load webhook route (excluded from CSRF by the consuming app)
        $this->loadRoutesFrom(__DIR__ . '/../routes/webhook.php');

        // Register artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProvisionVirtualAccountsCommand::class,
            ]);
        }
    }
}
