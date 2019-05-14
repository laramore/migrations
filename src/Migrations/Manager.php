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

use Laramore\Meta;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use DB;

class Manager
{
    protected $path;
    protected $actualNodes;
    protected $wantedNodes;
    protected $missingNodes;
    protected $counter = 0;

    protected static $tableMetas;

    public function __construct()
    {
        $this->path = base_path('database/migrations');

        $this->loadWantedNodes();
        $this->loadActualNodes();
        $this->loadMissingNodes();
    }

    protected function loadWantedNodes()
    {
        $wantedNodes = [];
        static::$tableMetas = [];

        foreach (Meta::getMetas()->toArray() as $meta) {
            $nodes = [];
            $tableName = $meta->getTableName();
            static::$tableMetas[$tableName] = $meta;

            foreach ($meta->getMigrationProperties() as $attname => $properties) {
                $nodes[] = new Command($tableName, $attname, $properties);
            }

            foreach ($meta->getMigrationContraints() as $attname => $data) {
                $nodes[] = new Contraint($tableName, $attname, $data['needs'], $data['properties']);
            }

            if (count($nodes)) {
                $wantedNodes[] = new MetaNode($nodes, $meta);
            }
        }

        $this->wantedNodes = new Node($wantedNodes);
        $this->wantedNodes->organize()->optimize();
    }

    protected function loadActualNodes()
    {
        $doctrine = DB::connection()->getDoctrineSchemaManager();
        $unvalidTable = config('database.migrations');
        $actualNodes = [];

        foreach ($doctrine->listTables() as $table) {
            if ($table->getName() !== $unvalidTable) {
                $actualNodes[] = new DatabaseNode($table);
            }
        }

        $this->actualNodes = new Node($actualNodes);
        $this->actualNodes->organize()->optimize();
    }

    protected function loadMissingNodes()
    {
        $this->missingNodes = $this->wantedNodes->diff($this->actualNodes);
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
        return $this->getFieldsFromNodes($this->wantedNodes);
    }

    public function getDatabaseFields()
    {
        return $this->getFieldsFromNodes($this->actualNodes);
    }

    public static function getTableMetas()
    {
        return static::$tableMetas;
    }

    public static function getTableMeta(string $tableName)
    {
        return static::$tableMetas[$tableName];
    }

    protected function generateMigrationFile($viewName, $data, $path)
    {
        file_put_contents($path, view($viewName, array_merge([
            'php' => '<?php',
            'blueprintVar' => '$table',
        ], $data))->render());
    }

    protected function getCounter()
    {
        $counter = (string) $this->counter++;

        for ($i = (6 - strlen($counter)); $i > 0; $i--) {
            $counter = '0'.$counter;
        }

        return $counter;
    }

    protected function generateMigration(MetaNode $metaNode)
    {
        $meta = $metaNode->getMeta();

        $data = [
            'date' => now(),
            'type' => ($type = $metaNode->getType()) === 'update' ? 'table' : $type,
            'model' => $model = $meta->getModelClassName(),
            'table' => $table = $meta->getTableName(),
            'name' => $name = ucfirst($type).ucfirst($table).'Table',
            'fields' => $metaNode->getFieldNodes(),
            'contraints' => array_map(function ($contraint) {
                return $contraint->getCommand();
            }, $metaNode->getContraintNodes()),
        ];

        $fileName = date('Y_m_d_').$this->getCounter().'_'.Str::snake($name);

        $this->generateMigrationFile('laramore::migration', $data, $this->path.'/'.$fileName.'.php');

        return $fileName;
    }

    public function clearMigrations()
    {
        (new Filesystem)->cleanDirectory($this->path);
    }

    public function generateMigrations()
    {
        $generatedFiles = [];

        foreach ($this->missingNodes->getNodes() as $node) {
            $generatedFiles[] = $this->generateMigration($node);
        }

        return $generatedFiles;
    }
}
