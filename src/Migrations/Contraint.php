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

class Contraint extends AbstractCommand
{
    protected $tableName;
    protected $needs;
    protected $command;
    protected $contraint = 'foreign';

    public function __construct(string $tableName, $attname, array $needs, array $properties)
    {
        $this->tableName = $tableName;
        $this->needs = $needs;
        $this->command = new Command($tableName, $this->contraint, $attname, $properties);
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

    public function getField()
    {
        return $this->getCommand()->getField().'*';
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
        return str_replace(['-', '.'], '_', strtolower($this->getTableName().'_'.implode('_', (array) $this->getAttname()).'_'.$this->contraint));
    }

    protected function generateReverse(): AbstractCommand
    {
        return new DropCommand($this->getTableName(), 'drop'.ucfirst($this->contraint), $this->getAttname(), $this->getIndexName(), $this->getCommand());
    }
}
