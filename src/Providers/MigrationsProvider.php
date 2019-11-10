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
use Illuminate\Database\Migrations\Migrator;
use Laramore\Interfaces\IsALaramoreProvider;
use Laramore\Traits\Providers\MergesConfig;
use Laramore\Commands\{
    MigrateClear, MigrateGenerate
};
use Laramore\Migrations\MigrationManager;
use Migrations, Types;

class MigrationsProvider extends ServiceProvider implements IsALaramoreProvider
{
    use MergesConfig;

    /**
     * App migrator.
     *
     * @var Migrator
     */
    protected static $migrator;

    /**
     * Type manager.
     *
     * @var MigrationManager
     */
    protected static $manager;

    /**
     * Before booting, create our definition for migrations.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/types.php', 'types',
        );

        $this->app->singleton('Migrations', function() {
            return static::getManager();
        });
        $this->app->booting([$this, 'bootingCallback']);
    }

    /**
     * During booting, load our migration views, Migration singletons.
     *
     * @return void
     */
    public function boot()
    {
        static::$migrator = $this->app->migrator;

        $this->loadViews();
        $this->loadCommands();
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
     * Add migration commands.
     *
     * @return void
     */
    public function loadCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateClear::class,
                MigrateGenerate::class,
            ]);
        }
    }

    /**
     * Return the default values for the manager of this provider.
     *
     * @return Migrator
     */
    public static function getDefaults(): Migrator
    {
        return static::$migrator;
    }

    /**
     * Generate the corresponded manager.
     *
     * @return void
     */
    protected static function generateManager()
    {
        static::$manager = new MigrationManager(static::getDefaults());
    }

    /**
     * Return the generated manager for this provider.
     *
     * @return object
     */
    public static function getManager(): object
    {
        if (\is_null(static::$manager)) {
            static::generateManager();
        }

        return static::$manager;
    }

    /**
     * Lock all managers after booting.
     *
     * @return void
     */
    public function bootedCallback()
    {
        static::getManager()->lock();
    }

    /**
     * Before booting, add a new type definition and fix increment default value.
     *
     * @return void
     */
    public function bootingCallback()
    {
        Types::define('migration_type');
        Types::define('migration_properties', []);
    }
}
