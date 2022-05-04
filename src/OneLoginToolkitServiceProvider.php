<?php


namespace OneLoginToolkit;

use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class OneLoginToolkitServiceProvider extends BaseServiceProvider {
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom($this->configPath(), 'onelogin');
        $this->loadMigrationsFrom($this->migrationsPath());
        $this->loadRoutesFrom($this->routesPath());
        $this->commands([Commands\OneLogin::class]);

        $this->publishes([
            __DIR__.'/Controllers/OneLoginController.php' => app_path('Http/Controllers/OneLoginController.php'),
            __DIR__.'/Models/OneLoginSite.php' => app_path('Models/OneLoginSite.php')
        ], 'courier-config');
    }

    /**
     * Register the config for publishing
     *
     */
    public function boot()
    {
        if ($this->app instanceof LaravelApplication && $this->app->runningInConsole()) {
            $this->publishes([$this->configPath() => config_path('onelogin.php')], 'cors');
        }
    }

    /**
     * Set the config path
     *
     * @return string
     */
    protected function configPath(): string
    {
        return __DIR__ . '/Config/onelogin.php';
    }

    /**
     * Set the migrations path
     *
     * @return string
     */
    protected function migrationsPath(): string
    {
        return __DIR__ . '/Migrations/2022_04_16_000000_create_onelogin_site_table.php';
    }

    /**
     * Set the routes path
     *
     * @return string
     */
    protected function routesPath(): string
    {
        return __DIR__ . '/Routes/SAML.php';
    }
}
