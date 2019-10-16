<?php
/**
 * Clear all migrations.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Commands;

use Illuminate\Console\Command;
use Laramore\Facades\MigrationManager;

class MigrateClear extends Command
{
    /**
     * @var string
     */
    protected $signature = 'migrate:clear';

    /**
     * @var string
     */
    protected $description = 'Remove all migrations';

    /**
     * Exécution de la commande.
     *
     * @return mixed
     */
    public function handle()
    {
        $removedFiles = MigrationManager::clearMigrations();

        if (\count($removedFiles)) {
            foreach ($removedFiles as $removedFile) {
                $this->line("<info>Removed:</info> {$removedFile}");
            }
        } else {
            $this->warn('No migrations to remove');
        }
    }
}
