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
use Laramore\Fields\{
    BaseField, AttributeField
};
use Laramore\Fields\Constraint\Foreign;
use Laramore\Interfaces\IsALaramoreManager;
use Laramore\Migrations\{
    Command, Constraint, MetaNode, Node, Index, SchemaNode
};
use Laramore\Traits\IsLocked;
use Laramore\Meta;
use Laramore\Facades\{
    Meta as MetaManager, Rule
};

class MigrationManager implements IsALaramoreManager
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
     * Return the main properties of a field.
     *
     * @param BaseField $field
     * @return array
     */
    protected function getFieldProperties(BaseField $field): array
    {
        if (\method_exists($field, 'getMigrationPropertyKeys')) {
            $keys = \call_user_func([$field, 'getMigrationPropertyKeys']);
        } else {
            $keys = $field->getType()->getMigrationPropertyKeys();
        }

        if (\method_exists($field, 'getMigrationProperties')) {
            return \call_user_func([$field, 'getMigrationProperties'], $keys);
        }

        $properties = [];

        foreach ($keys as $property) {
            $nameKey = explode(':', $property);
            $name = $nameKey[0];
            $key = ($nameKey[1] ?? $name);

            if (Rule::has($snakeKey = Str::snake($key))) {
                if ($field->hasRule($snakeKey)) {
                    $properties[$name] = true;
                }
            } else if (\method_exists($field, $method = 'get'.\ucfirst($key))) {
                $properties[$name] = \call_user_func([$field, $method]);
            } else if (!is_null($value = $field->getProperty($key, false))) {
                $properties[$name] = $value;
            }
        }

        return $properties;
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
        foreach ($meta->getFields() as $field) {
            $type = $field->getType()->getMigrationType();
            if (\is_null($type)) {
                continue;
            }

            $properties = $this->getFieldProperties($field);
            $nodes[] = $command = new Command($tableName, $type, $field->getAttname(), $properties);

            foreach ($field->getConstraints() as $constraint) {
                if ($constraint->count() > 1) {
                    continue;
                }

                if ($constraint->getConstraintName() === 'primary'
                    && $constraint->all()[0]->getType()->getMigrationType() === 'increments') {
                    continue;
                }

                $command->setProperty($constraint->getConstraintName(), $constraint->hasName() ? $constraint->getName() : true);
            }
        }

        // Generate a constraint for each composite relations.
        foreach ($meta->getConstraintHandler()->all() as $constraint) {
            if ($constraint instanceof Foreign) {
                $needs = \array_map(function (AttributeField $field) {
                    return [
                        'table' => $field->getMeta()->getTableName(),
                        'field' => $field->attname,
                    ];
                }, $constraint->all());

                $field = $constraint->getOnField();

                $properties = [
                    'references' => $field->attname,
                    'on' => $field->getMeta()->getTableName(),
                ];

                $nodes[] = new Constraint($tableName, $constraint->getOffField()->attname, $needs, $properties);
            } else {
                if ($constraint->count() === 1) {
                    continue;
                }

                $nodes[] = new Index($tableName, $constraint->getConstraintName(), \array_map(function ($field) {
                    return $field->attname;
                }, $constraint->all()));
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
            $migration = $this->migrator->resolve($this->migrator->getMigrationName($migrationFile));

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
