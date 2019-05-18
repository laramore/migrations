<?php
/**
 * Correspond to a migration contraint.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Migrations;

use Laramore\Meta;

class Contraint
{
    protected $tableName;
    protected $needs;
    protected $command;

    public function __construct(string $tableName, string $attname, array $needs, array $properties)
    {
        $this->tableName = $tableName;
        $this->needs = $needs;
        $this->command = new Command($tableName, 'foreign', $attname, $properties);
    }

    public function getNodes()
    {
        return $this->nodes;
    }

    public function getTableName()
    {
        return $this->tableName;
    }

    public function getAttname()
    {
        return $this->command->getAttname();
    }

    public function getCommand()
    {
        return $this->command;
    }

    public function getNeeds()
    {
        return $this->needs;
    }

    public function getFields(): array
    {
        $fields = [];

        foreach ($this->needs as $need) {
            $fields[] = $need['table'].'.'.$need['field'];
        }

        return array_unique($fields);
    }

    /**
     * Create a default index name for the table.
     *
     * @param  string $type
     * @param  array  $columns
     * @return string
     */
    protected function getIndexName()
    {
        return str_replace(['-', '.'], '_', strtolower($this->getTableName().'_'.$this->getAttname().'_foreign'));
    }

    public function getReverse()
    {
        return new Command($this->getTableName(), 'dropForeign', $this->getIndexName(), []);
    }
}
