<?php
/**
 * Handle migrations.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Database\Migrations\Migrator;
use Laramore\Facades\{
    MetaManager, TypeManager
};
use Laramore\Fields\Foreign;
use Laramore\Interfaces\IsAPrimaryField;
use Laramore\Migrations\{
    Command, Constraint, DatabaseNode, MetaNode, Node, Index, SchemaNode,
};
use Illuminate\Support\Str;

class MigrationManager
{
    protected $path;
    protected $migrator;
    protected $loadingMigrations = false;

    protected $fileCounter = 0;
    protected $migrationCounter = 0;
    protected $currentFileCounter = 0;

    protected $actualNode;
    protected $wantedNode;
    protected $missingNode;

    public function __construct(Migrator $migrator)
    {
        $this->migrator = $migrator;

        $this->path = base_path('database'.DIRECTORY_SEPARATOR.'migrations');
        $this->migrationFiles = $this->migrator->getMigrationFiles($this->path);

        $this->calculateMigrationCounter();
    }

    protected function calculateMigrationCounter()
    {
        if ($this->fileCounter = count($this->migrationFiles)) {
            $this->migrationCounter = (((integer) substr(explode('_', end($this->migrationFiles))[3], 0, 3)) + 1);
        }
    }

    protected function getStringCounter(int $counter)
    {
        $counter = (string) $counter;

        for ($i = (3 - strlen($counter)); $i > 0; $i--) {
            $counter = '0'.$counter;
        }

        return $counter;
    }

    protected function getNodesFromMeta(Meta $meta)
    {
        $nodes = [];
        $tableName = $meta->getTableName();

        foreach ($meta->getFields() as $field) {
            $nodes[] = new Command($tableName, $field->getType()->migration, $field->getAttname(), $field->getProperties());

            if ($field instanceof IsAPrimaryField && $field->getType() !== TypeManager::increment()) {
                end($nodes)->setProperty('primary', true);
            }
        }

        foreach ($meta->getComposites() as $composite) {
            if ($composite instanceof Foreign) {
                $needs = [
                    [
                        'table' => $toTableName = $composite->on::getMeta()->getTableName(),
                        'field' => $composite->to,
                    ],
                    [
                        'table' => $tableName,
                        'field' => $attname = $composite->from,
                    ],
                ];

                $properties = [
                    'references' => $composite->to,
                    'on' => $toTableName
                ];

                $nodes[] = new Constraint($tableName, $attname, $needs, $properties);
            }
        }

        if (is_array($primaries = $meta->getPrimary())) {
            $nodes[] = new Index($tableName, 'primary', array_map(function ($field) {
                return $field->attname;
            }, $primaries));
        }

        foreach ($meta->getUniques() as $unique) {
            $nodes[] = new Index($tableName, 'unique', array_map(function ($field) {
                return $field->attname;
            }, $unique));
        }

        foreach ($meta->getIndexes() as $index) {
            $nodes[] = new Index($tableName, 'index', array_map(function ($field) {
                return $field->attname;
            }, $index));
        }

        return $nodes;
    }

    public function isLoadingMigrations()
    {
        return $this->loadingMigrations;
    }

    protected function loadWantedNode()
    {
        $wantedNode = [];

        foreach (MetaManager::all() as $meta) {
            $nodes = $this->getNodesFromMeta($meta);

            if (count($nodes)) {
                $wantedNode[] = new MetaNode($nodes, $meta->getTableName());
            }
        }

        $this->wantedNode = new Node($wantedNode);
        $this->wantedNode->organize()->optimize();
    }

    protected function loadActualNode()
    {
        $this->loadingMigrations = true;

        $this->migrator->requireFiles($this->migrationFiles);

        foreach ($this->migrationFiles as $migrationFile) {
            $migration = $this->migrator->resolve($this->migrator->getMigrationName($migrationFile));

            if (method_exists($migration, 'up')) {
                $migration->up();
            }
        }

        $this->loadingMigrations = false;

        $this->actualNode = new SchemaNode();
        $this->actualNode->organize()->optimize();
    }

    protected function loadMissingNode()
    {
        $this->missingNode = $this->getWantedNode()->diff($this->getActualNode());
        $this->missingNode->organize()->optimize();
    }

    public function getWantedNode()
    {
        if (is_null($this->wantedNode)) {
            $this->loadWantedNode();
        }

        return $this->wantedNode;
    }

    public function getActualNode()
    {
        if (is_null($this->actualNode)) {
            $this->loadActualNode();
        }

        return $this->actualNode;
    }

    public function getMissingNode()
    {
        if (is_null($this->missingNode)) {
            $this->loadMissingNode();
        }

        return $this->missingNode;
    }

    protected function getFieldsFromNodes(array $nodes)
    {
        $table = [];

        foreach ($nodes as $tableName => $node) {
            $table[$tableName] = $this->getFieldsFromNode($node);
        }

        return $table;
    }

    public function getDefinedFields()
    {
        return $this->getFieldsFromNodes($this->getWantedNode());
    }

    public function getDatabaseFields()
    {
        return $this->getFieldsFromNodes($this->getActualNode());
    }

    protected function generateMigrationFile($viewName, $data, $path)
    {
        file_put_contents($path, view($viewName, array_merge([
            'php' => '<?php',
            'blueprintVar' => '$table',
        ], $data))->render());
    }

    protected function generateMigration(MetaNode $metaNode)
    {
        $type = $metaNode->getType();
        $counters = $this->getStringCounter($this->migrationCounter).$this->getStringCounter($this->currentFileCounter++);
        $fileCounter = $this->getStringCounter($this->fileCounter++);

        $data = [
            'date' => now(),
            'type' => $type,
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

    public function clearMigrations()
    {
        (new Filesystem)->cleanDirectory($this->path);
    }

    public function generateMigrations()
    {
        $generatedFiles = [];

        foreach ($this->getMissingNode()->getNodes() as $node) {
            $generatedFiles[] = $this->generateMigration($node);
        }

        return $generatedFiles;
    }
}
