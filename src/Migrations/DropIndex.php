<?php
/**
 * Correspond to a drop constraint.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Migrations;

use Laramore\Interfaces\Migration\IsADropCommand;

class DropIndex extends Index implements IsADropCommand
{
    /**
     * Create a new drop command for a specific key.
     *
     * @param string $tableName
     * @param string $type
     * @param string $key
     * @param Index  $reversedIndex
     */
    public function __construct(string $tableName, string $type, string $key, Index $reversedIndex=null)
    {
        parent::__construct($tableName, $type, []);

        $this->attname = $key;
        $name = \is_null($reversedIndex) ? $key : $reversedIndex->getIndexName();
        $this->command = new Command($tableName, $this->constraint, $name, []);

        if (!\is_null($reversedIndex)) {
            $this->reverse = $reversedIndex->getCommand();
        }
    }

    /**
     * Create a default index name for the table.
     *
     * @return string
     */
    public function getIndexName(): string
    {
        return $this->getCommand()->getAttname();
    }

    /**
     * Return the attribute name
     *
     * @return string|array
     */
    public function getAttname()
    {
        return $this->attname;
    }

    /**
     * Return a distinct field format.
     *
     * @return string
     */
    public function getField(): string
    {
        return $this->tableName.'.'.$this->getAttname().'_'.$this->constraint.'+';
    }
}
