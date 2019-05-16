<?php
/**
 * Generate missing migrations.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Laramore\Facades\MigrationManager;

class MigrateGenerate extends Command
{
    /**
     * @var string
     */
    protected $signature = 'migrate:generate
        {--database= : The database connection to use}
        {--path= : The path to the migrations files to use}
        {--realpath= : Indicate any provided migration file paths are pre-resolved absolute paths}
    ';

    /**
     * @var string
     */
    protected $description = 'Generate missing migrations';

    /**
     * The migrator instance.
     *
     * @var \Illuminate\Database\Migrations\Migrator
     */
    protected $migrator;

    /**
     * Create a new migration rollback command instance.
     *
     * @param  \Illuminate\Database\Migrations\Migrator $migrator
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->migrator = app('migrator');
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->migrator->setConnection($this->option('database'));
        $files = $this->migrator->getMigrationFiles($this->getMigrationPaths());

        if ($this->migrator->repositoryExists()) {
            $batches = $this->migrator->getRepository()->getMigrationBatches();
            $ranMigrations = [];

            foreach ($batches as $name => $status) {
                if ($status > 0) {
                    $ranMigrations[] = $name;
                }
            }

            if (count(array_diff(array_keys($files), $ranMigrations))) {
                $this->error('All migrations are not launched');

                return false;
            }
        } else {
            if (count($files)) {
                $this->error('No migrations were launched. Clear them or run all of them before generating new ones');

                return false;
            }
        }

        $generatedFiles = MigrationManager::generateMigrations();

        if (count($generatedFiles)) {
            foreach ($generatedFiles as $generatedFile) {
                $this->line("<info>Generated Migration:</info> {$generatedFile}");
            }
        } else {
            $this->warn('No new migrations to generate');
        }
    }

    /**
     * Get all of the migration paths.
     *
     * @return array
     */
    protected function getMigrationPaths()
    {
        // Here, we will check to see if a path option has been defined. If it has we will
        // use the path relative to the root of the installation folder so our database
        // migrations may be run for any customized path from within the application.
        if ($this->input->hasOption('path') && $this->option('path')) {
            return collect($this->option('path'))->map(function ($path) {
                return ! $this->usingRealPath() ? $this->laravel->basePath().DIRECTORY_SEPARATOR.$path : $path;
            })->all();
        }

        return array_merge(
            $this->migrator->paths(), [$this->getMigrationPath()]
        );
    }

    /**
     * Determine if the given path(s) are pre-resolved "real" paths.
     *
     * @return boolean
     */
    protected function usingRealPath()
    {
        return $this->input->hasOption('realpath') && $this->option('realpath');
    }

    /**
     * Get the path to the migration directory.
     *
     * @return string
     */
    protected function getMigrationPath()
    {
        return $this->laravel->databasePath().DIRECTORY_SEPARATOR.'migrations';
    }
}
