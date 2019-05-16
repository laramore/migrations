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

use Illuminate\Filesystem\Filesystem;
use Laramore\Facades\MetaManager;
use Laramore\Fields\Foreign;
use Laramore\Meta;
use Illuminate\Support\Str;
use DB;

class Manager
{
    protected $path;
    protected $counter;
    protected $actualNodes;
    protected $wantedNodes;
    protected $missingNodes;

    public function __construct()
    {
        $this->path = base_path('database'.DIRECTORY_SEPARATOR.'migrations');
        $this->counter = count(app('migrator')->getMigrationFiles($this->path));

        $this->loadWantedNodes();
        $this->loadActualNodes();
        $this->loadMissingNodes();
    }

    protected function getNodesFromMeta(Meta $meta)
    {
        $nodes = [];
        $tableName = $meta->getTableName();

        foreach ($meta->getFields() as $field) {
            $nodes[] = new Command($tableName, $field->type, $field->attname, $field->getProperties());
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

                $nodes[] = new Contraint($tableName, $attname, $needs, $properties);
            }
        }

        return $nodes;
    }

    protected function loadWantedNodes()
    {
        $wantedNodes = [];

        foreach (MetaManager::getMetas() as $meta) {
            $nodes = $this->getNodesFromMeta($meta);

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

        foreach ($this->missingNodes->getNodes() as $node) {
            $generatedFiles[] = $this->generateMigration($node);
        }

        return $generatedFiles;
    }
}
