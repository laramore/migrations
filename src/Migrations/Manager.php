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

class Manager
{
    protected $path;
    protected $actualNodes;
    protected $wantedNodes;

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

    protected function generateMigration(Meta $meta)
    {
        $data = [
            'date' => now(),
            'model' => $model = $meta->getModelClassName(),
            'name' => 'Create'.ucfirst($model).'Table',
            'table' => $table = $meta->getTableName(),
            'fields' => $meta->getMigrationProperties(),
            'contraints' => $meta->getMigrationContraints(),
        ];

        $this->generateMigrationFile('laramore::migration', $data, $this->path.'/'.date('Y_m_d_Hsi_').$table.'.php');
    }

    public function generateMigrations()
    {
        foreach (Meta::getMetas() as $meta) {
            $this->generateMigration($meta);
        }
    }
}
