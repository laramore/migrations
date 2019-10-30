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
use Laramore\MigrationManager;
use Types;

class LaramoreMigrations extends ServiceProvider
{
    /**
     * Before booting, create our definition for migrations.
     *
     * @return void
     */
    public function register()
    {
        $this->app->booting([$this, 'bootingCallback']);
    }

    /**
     * During booting, load our migration views, Migration singletons.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViews();
        $this->addSingletons();
    }

    /**
     * Laod all views required for the migrations generation.
     *
     * @return void
     */
    protected function loadViews()
    {
        $viewPath = __DIR__.'/../../views';
        $this->loadViewsFrom($viewPath, 'laramore');

        $this->publishes([
            $viewPath => resource_path('views/vendor/laramore'),
        ]);
    }

    /**
     * Add Migrations as a singleton.
     *
     * @return void
     */
    protected function addSingletons()
    {
        $this->app->singleton('Migrations', function() {
            return new MigrationManager($this->app['migrator']);
        });
    }

    /**
     * Before booting, add a new type definition and fix increment default value.
     *
     * @return void
     */
    public function bootingCallback()
    {
        if (Types::has('increment')) {
            $incType = Types::increment();

            if (!$incType->has('migration')) {
                $incType->migration = 'increments';
            }
        }

        Types::define('migration');
    }
}
