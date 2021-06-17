<?php

namespace Importaremx\Facturapuntocom;

use Illuminate\Support\ServiceProvider;
use Illuminate\Filesystem\Filesystem;

class FacturapuntocomServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(Filesystem $filesystem): void
    {

        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'importaremx');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'importaremx');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/facturapuntocom.php', 'facturapuntocom');

        // Register the service the package provides.
        $this->app->singleton('facturapuntocom', function ($app) {
            return new Facturapuntocom;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['facturapuntocom'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__.'/../config/facturapuntocom.php' => config_path('facturapuntocom.php'),
        ], 'facturapuntocom.config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations')
        ], 'facturapuntocom.migrations');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/importaremx'),
        ], 'facturapuntocom.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/importaremx'),
        ], 'facturapuntocom.views');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/importaremx'),
        ], 'facturapuntocom.views');*/

        // Registering package commands.
        // $this->commands([]);
    }

}
