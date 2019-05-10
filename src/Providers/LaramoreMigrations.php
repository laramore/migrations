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
use Laramore\Commands\MigrateGenerate;

class LaramoreMigrations extends ServiceProvider
{
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
    }
}