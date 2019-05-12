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

class Manager
{
    protected $path;
    protected $actualNodes;
    protected $wantedNodes;
    protected $counter = 0;

    public function __construct()
    {
        $this->path = base_path('database/migrations');

        $this->loadActualNodes();
        $this->loadWantedNodes();
    }

    protected function loadActualNodes()
    {
        $this->actualNodes = [];
    }

    protected function loadWantedNodes()
    {
        $wantedNodes = [];

        foreach (Meta::getMetas()->toArray() as $meta) {
            $nodes = [];

            foreach ($meta->getMigrationProperties() as $attname => $properties) {
                $nodes[] = new Command($meta, $attname, $properties);
            }

            if (count($nodes)) {
                $wantedNodes[] = new MetaNode($nodes, $meta);
            }

            foreach ($meta->getMigrationContraints() as $attname => $data) {
                $wantedNodes[] = new Contraint($meta, $attname, $data['needs'], $data['properties']);
            }
        }

        $this->wantedNodes = new Node($wantedNodes);
        $this->wantedNodes->organize()->optimize();
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
            'name' => ucfirst($type).ucfirst($table).'Table',
            'fields' => $metaNode->getFieldNodes(),
            'contraints' => array_map(function ($contraint) {
                return $contraint->getCommand();
            }, $metaNode->getContraintNodes()),
        ];

        $this->generateMigrationFile('laramore::migration', $data, $this->path.'/'.date('Y_m_d_').$this->getCounter().'_'.$type.'_'.$table.'_table.php');
    }

    public function clearMigrations()
    {
        (new Filesystem)->cleanDirectory($this->path);
    }

    public function generateMigrations()
    {
        foreach ($this->wantedNodes->getNodes() as $node) {
            $this->generateMigration($node);
        }
    }
}
