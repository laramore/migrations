<?php
/**
 * Correspond to an index.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Migrations;

use Laramore\Meta;

class Index extends Constraint
{
    /**
     * Create a new index command.
     *
     * @param string $tableName
     * @param string $type
     * @param array  $fields
     */
    public function __construct(string $tableName, string $type, array $fields)
    {
        $this->constraint = $type;
        $needs = \array_map(function ($field) use ($tableName) {
            return [
                'table' => $tableName,
                'field' => $field,
            ];
        }, $fields);

        parent::__construct($tableName, [$fields], $needs, []);
    }

    /**
     * Create a default index name for the table.
     *
     * @return string
     */
    public function getIndexName(): string
    {
        return \str_replace(['-', '.'], '_', \implode('_', [
            \strtolower($this->getTableName()),
            \implode('_', $this->getAttname()[0]),
            $this->constraint,
        ]));
    }

    /**
     * Return a distinct field format.
     *
     * @return string
     */
    public function getField(): string
    {
        return $this->tableName.'.'.\implode('_', $this->getCommand()->getAttname()[0]).'_'.$this->constraint.'+';
    }

    /**
     * Generate a new reversed command.
     *
     * @return AbstractCommand
     */
    protected function generateReverse(): AbstractCommand
    {
        return new DropIndex($this->getTableName(), $this->constraint, $this->getIndexName(), $this);
    }
}
