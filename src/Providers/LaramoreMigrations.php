<?php
/**
 * Prepare the package.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Laramore\Facades\TypeManager;
use Laramore\MigrationManager;

class LaramoreMigrations extends ServiceProvider
{
    public function register()
    {
        $this->app->booting([$this, 'bootingCallback']);
    }

    /**
     * Load views
     *
     * @return void
     */
    public function boot()
    {
        $viewPath = __DIR__.'/../../views';
        $this->loadViewsFrom($viewPath, 'laramore');

        $this->publishes([
            $viewPath => resource_path('views/vendor/laramore'),
        ]);

        $this->app->singleton('MigrationManager', function() {
            return new MigrationManager($this->app['migrator']);
        });
    }

    public function bootingCallback()
    {
        TypeManager::define('migration');

        if (TypeManager::has('increment')) {
            TypeManager::increment()->migration = 'increments';
        }
    }
}
