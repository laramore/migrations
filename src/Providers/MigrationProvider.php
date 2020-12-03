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
use Illuminate\Database\Migrations\Migrator;
use Laramore\Contracts\Manager\LaramoreManager;
use Laramore\Traits\Provider\MergesConfig;
use Laramore\Commands\{
    MigrateClear, MigrateGenerate
};
use Laramore\Migrations\MigrationManager;
use Laramore\Facades\Migration;
use Laramore\Fields\BaseField;
use Laramore\Mixins\MigrationField;

class MigrationProvider extends ServiceProvider
{
    use MergesConfig;

    /**
     * App migrator.
     *
     * @var Migrator
     */
    protected static $migrator;

    /**
     * Before booting, create our definition for migration.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/field/constraint.php', 'field.constraint',
        );
        
        $this->mergeConfigFrom(
            __DIR__.'/../../config/field/migrations.php', 'field.migrations',
        );

        $this->app->singleton('migration', function() {
            return static::generateManager();
        });

        $this->app->booting([$this, 'bootingCallback']);
        $this->app->booted([$this, 'bootedCallback']);
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
     * Laod all views required for the migration generation.
     *
     * @return void
     */
    protected function loadViews()
    {
        $viewPath = __DIR__.'/../../resources/views';
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
     * @return LaramoreManager
     */
    public static function generateManager(): LaramoreManager
    {
        return new MigrationManager(static::getDefaults());
    }

    /**
     * Before booting, add a new type definition and fix increment default value.
     * If the manager is locked during booting we need to reset it.
     *
     * @return void
     */
    public function bootingCallback()
    {
        $this->setMacros();
    }

    /**
     * Add all required macros for validations.
     *
     * @return void
     */
    protected function setMacros()
    {
        BaseField::mixin(new MigrationField);
    }

    /**
     * Lock all managers after booting.
     *
     * @return void
     */
    public function bootedCallback()
    {
        Migration::lock();
    }
}
