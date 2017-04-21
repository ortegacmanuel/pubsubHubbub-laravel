<?php 

namespace Ortegacmanuel\PubsubhubbubLaravel;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Ortegacmanuel\ActivitystreamsLaravel\Activity;

class PubsubhubbubServiceProvider extends LaravelServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot() {

        $this->handleConfigs();
        $this->handleMigrations();
        // $this->handleViews();
        // $this->handleTranslations();
        $this->handleRoutes();

        Activity::observe(ActivityObserver::class);        
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {

        // Bind any implementations.

    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides() {

        return [];
    }

    private function handleConfigs() {

        $configPath = __DIR__ . '/../config/pubsubhubbub-laravel.php';

        $this->publishes([$configPath => config_path('pubsubhubbub-laravel.php')]);

        $this->mergeConfigFrom($configPath, 'pubsubhubbub-laravel');
    }

    private function handleTranslations() {

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'packagename');
    }

    private function handleViews() {

        $this->loadViewsFrom(__DIR__.'/../views', 'packagename');

        $this->publishes([__DIR__.'/../views' => base_path('resources/views/vendor/packagename')]);
    }

    private function handleMigrations() 
    {
        $this->loadMigrationsFrom(__DIR__.'/../migrations');
    }

    private function handleRoutes() 
    {
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
    }
}
