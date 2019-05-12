<?php
/**
 * Add migration commands.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Providers;

use Illuminate\Support\ServiceProvider;
use Laramore\Commands\{
    MigrateClear, MigrateGenerate
};

class MigrateCommands extends ServiceProvider
{
    /**
     * Add migration commands.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateClear::class,
                MigrateGenerate::class,
            ]);
        }
    }
}
