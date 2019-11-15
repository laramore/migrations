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
use Laramore\Interfaces\{
    IsALaramoreManager, IsALaramoreProvider
};
use Laramore\Traits\Provider\MergesConfig;
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
     * Migration manager.
     *
     * @var array
     */
    protected static $managers;

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
        return static::$migrator ?: app('migrator');
    }

    /**
     * Generate the corresponded manager.
     *
     * @param  string $key
     * @return IsALaramoreManager
     */
    public static function generateManager(string $key): IsALaramoreManager
    {
        return static::$managers[$key] = new MigrationManager(static::getDefaults());
    }

    /**
     * Return the generated manager for this provider.
     *
     * @return IsALaramoreManager
     */
    public static function getManager(): IsALaramoreManager
    {
        $appHash = \spl_object_hash(app());

        if (!isset(static::$managers[$appHash])) {
            return static::generateManager($appHash);
        }

        return static::$managers[$appHash];
    }

    /**
     * Before booting, add a new type definition and fix increment default value.
     * If the manager is locked during booting we need to reset it.
     *
     * @return void
     */
    public function bootingCallback()
    {
        Types::define('migration_type');
        Types::define('migration_properties', []);
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
}
