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
use Laramore\Facades\MigrationManager;

class MigrateGenerate extends Command
{
    /**
     * @var string
     */
    protected $signature = 'migrate:generate';

    /**
     * @var string
     */
    protected $description = 'Generate missing migrations';

    /**
     * Exécution de la commande.
     *
     * @return mixed
     */
    public function handle()
    {
        // For now, we clean everything before creating migrations.
        $this->call('migrate:clear');

        MigrationManager::generateMigrations();
    }
}
