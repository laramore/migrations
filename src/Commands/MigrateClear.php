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
use Migrations;

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
        $removedFiles = Migrations::clearMigrations();

        if (\count($removedFiles)) {
            foreach ($removedFiles as $removedFile) {
                $this->line("<info>Removed:</info> {$removedFile}");
            }
        } else {
            $this->warn('No migrations to remove');
        }
    }
}
