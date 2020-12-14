<?php
/**
 * Handle migrations.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Migrations;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Laramore\Contracts\Field\{
    AttributeField, IncrementField,
    Constraint\RelationalConstraint, Constraint\IndexableConstraint
};
use Laramore\Fields\Constraint\{
    BaseIndexableConstraint, BaseRelationalConstraint
};
use Laramore\Contracts\Manager\LaramoreManager;
use Laramore\Migrations\{
    Command, Constraint, MetaNode, Node, Index, SchemaNode
};
use Laramore\Traits\IsLocked;
use Laramore\Eloquent\Meta;
use Laramore\Facades\Meta as MetaManager;

class MigrationManager implements LaramoreManager
{
    use IsLocked;

    /**
     * Path were all migration files are stored.
     *
     * @var string
     */
    protected $path;

    /**
     * Migrator instance.
     *
     * @var Migrator
     */
    protected $migrator;

    /**
     * Indicate if Laramore is loading migrations.
     *
     * @var boolean
     */
    protected $loadingMigrations = false;

    /**
     * Number of migration files.
     *
     * @var integer
     */
    protected $fileCounter = 0;

    /**
     * Number of run migrations.
     *
     * @var integer
     */
    protected $migrationCounter = 0;

    /**
     * Current generated file for this migration.
     *
     * @var integer
     */
    protected $currentFileCounter = 0;

    /**
     * Actual migration node.
     *
     * @var SchemaNode
     */
    protected $actualNode;

    /**
     * Wanted node generated from models.
     *
     * @var Node
     */
    protected $wantedNode;

    /**
     * Missing node to get the wanted one.
     *
     * @var Node
     */
    protected $missingNode;

    /**
     * Create the migration manager.
     *
     * @param Migrator $migrator
     */
    public function __construct(Migrator $migrator)
    {
        $this->migrator = $migrator;

        $this->path = base_path('database'.DIRECTORY_SEPARATOR.'migrations');
        $this->migrationFiles = $this->migrator->getMigrationFiles($this->path);

        $this->calculateMigrationCounter();
    }

    /**
     * Calculate the number of run migrations.
     *
     * @return void
     */
    protected function calculateMigrationCounter()
    {
        if ($this->fileCounter = count($this->migrationFiles)) {
            $this->migrationCounter = (((integer) substr(explode('_', end($this->migrationFiles))[3], 0, 3)) + 1);
        }
    }

    /**
     * Transform an integer counter to string.
     *
     * @param  integer $counter
     * @return string
     */
    protected function getStringCounter(int $counter): string
    {
        $counter = (string) $counter;

        for ($i = (3 - strlen($counter)); $i > 0; $i--) {
            $counter = '0'.$counter;
        }

        return $counter;
    }

    /**
     * Generate sub nodes/commands/constraints from a meta.
     *
     * @param  Meta $meta
     * @return array
     */
    protected function getNodesFromMeta(Meta $meta): array
    {
        $nodes = [];
        $tableName = $meta->getTableName();

        // Generate a command for each field.
        foreach ($meta->getFields(AttributeField::class) as $field) {
            $type = $field->getMigrationType();

            $properties = $field->getMigrationProperties();
            $nodes[] = $command = new Command($tableName, $type, $field->getAttname(), $properties);
            $constraints = \array_merge(...\array_values($field->getConstraintHandler()->all()));

            if ($field instanceof IncrementField) {
                continue;
            }

            foreach ($constraints as $constraint) {
                if ($constraint->isComposed() || ($constraint instanceof RelationalConstraint)) {
                    continue;
                }

                $command->setProperty($constraint->getConstraintType(), $constraint->hasName() ? $constraint->getName() : true);
            }
        }

        $constraints = \array_merge(...\array_values($meta->getConstraintHandler()->all()));

        // Generate a constraint for each composite relations.
        foreach ($constraints as $constraint) {
            $type = $constraint->getConstraintType();

            if ($constraint instanceof RelationalConstraint && \in_array($type, BaseRelationalConstraint::$migrable)) {
                if ($constraint->getSourceAttribute()->getMeta() !== $meta) {
                    continue;
                }

                foreach ($constraint->getSourceAttributes() as $index => $sourceField) {
                    $targetField = $constraint->getTargetAttributes()[$index];

                    $needs = [
                        [
                            'table' => $sourceField->getMeta()->getTableName(),
                            'field' => $sourceField->getNative(),
                        ],
                        [
                            'table' => $targetField->getMeta()->getTableName(),
                            'field' => $targetField->getNative(),
                        ]
                    ];

                    $properties = [
                        'references' => $targetField->getNative(),
                        'on' => $targetField->getMeta()->getTableName(),
                    ];

                    $nodes[] = new Constraint($tableName, $sourceField->getNative(), $needs, $properties);
                }
            } else if ($constraint instanceof IndexableConstraint && \in_array($type, BaseIndexableConstraint::$migrable) && $constraint->isComposed()) {
                $nodes[] = new Index($tableName, $constraint->getConstraintType(), \array_map(function ($field) {
                    return $field->getNative();
                }, $constraint->getAttributes()));
            }
        }

        return $nodes;
    }

    /**
     * Indicate if Laramore is loading migrations.
     *
     * @return boolean
     */
    public function isLoadingMigrations(): bool
    {
        return $this->loadingMigrations;
    }

    /**
     * Load all wanted nodes from models.
     *
     * @return void
     */
    protected function loadWantedNode()
    {
        $wantedNode = [];

        foreach (MetaManager::all() as $meta) {
            $nodes = $this->getNodesFromMeta($meta);

            if (\count($nodes)) {
                $wantedNode[] = new MetaNode($nodes, $meta->getTableName());
            }
        }

        $this->wantedNode = new Node($wantedNode);
        $this->wantedNode->organize()->optimize();
    }

    /**
     * Load all actual nodes from migrations.
     *
     * @return void
     */
    protected function loadActualNode()
    {
        $this->loadingMigrations = true;

        $this->migrator->requireFiles($this->migrationFiles);

        foreach ($this->migrationFiles as $migrationFile) {
            $migration = $this->migrator->resolve($this->migrator->getMigrationType($migrationFile));

            if (\method_exists($migration, 'up')) {
                $migration->up();
            }
        }

        $this->loadingMigrations = false;

        $this->actualNode = new SchemaNode();
        $this->actualNode->organize()->optimize();
    }

    /**
     * Generate the difference between the actual and wanted nodes.
     *
     * @return void
     */
    protected function loadMissingNode()
    {
        $this->missingNode = $this->getWantedNode()->diff($this->getActualNode());
        $this->missingNode->organize()->optimize();
    }

    /**
     * Return the wanted node.
     *
     * @return Node
     */
    public function getWantedNode(): Node
    {
        if (is_null($this->wantedNode)) {
            $this->loadWantedNode();
        }

        return $this->wantedNode;
    }

    /**
     * Return the actual node.
     *
     * @return SchemaNode
     */
    public function getActualNode(): SchemaNode
    {
        if (is_null($this->actualNode)) {
            $this->loadActualNode();
        }

        return $this->actualNode;
    }

    /**
     * Return the missing node.
     *
     * @return Node
     */
    public function getMissingNode()
    {
        if (is_null($this->missingNode)) {
            $this->loadMissingNode();
        }

        return $this->missingNode;
    }

    /**
     * Generate a migration file based on data given.
     *
     * @param  string $viewName
     * @param  array  $data
     * @param  string $path
     * @return void
     */
    protected function generateMigrationFile(string $viewName, array $data, string $path)
    {
        \file_put_contents($path, view($viewName, $data)->render());
    }

    /**
     * Generate a migration for a specific meta node.
     *
     * @param  MetaNode $metaNode
     * @return string    Generated file.
     */
    protected function generateMigration(MetaNode $metaNode)
    {
        $type = $metaNode->getType();
        $counters = $this->getStringCounter($this->migrationCounter).$this->getStringCounter($this->currentFileCounter++);
        $fileCounter = $this->getStringCounter($this->fileCounter++);

        $data = [
            'date' => now(),
            'table' => $table = $metaNode->getTableName(),
            'up' => $metaNode->getUp(),
            'down' => $metaNode->getDown(),
            'name' => ($name = ucfirst($type).ucfirst(Str::camel($table)).'Table').$fileCounter,
        ];

        if ($type !== 'delete') {
            $data['model'] = $metaNode->getMeta()->getModelClass();
        }

        $fileName = date('Y_m_d_').$counters.'_'.Str::snake($name).'_'.$fileCounter;

        $this->generateMigrationFile('laramore::migration', $data, $this->path.DIRECTORY_SEPARATOR.$fileName.'.php');

        return $fileName;
    }

    /**
     * Clear all migrations.
     *
     * @return array  All files cleared.
     */
    public function clearMigrations()
    {
        $fs = new Filesystem();
        $regex = "#$this->path/?(.*)\.php#";

        return \array_map(function ($filePath) use ($fs, $regex) {
            $fs->delete($filePath);
            \preg_match($regex, $filePath, $matches);

            return $matches[1];
        }, $this->migrationFiles);
    }

    /**
     * Generate all missing migrations.
     *
     * @return array  All files generated.
     */
    public function generateMigrations()
    {
        $generatedFiles = [];

        foreach ($this->getMissingNode()->getNodes() as $node) {
            $generatedFiles[] = $this->generateMigration($node);
        }

        return $generatedFiles;
    }

    /**
     * No actions during locking.
     *
     * @return void
     */
    protected function locking()
    {

    }
}
