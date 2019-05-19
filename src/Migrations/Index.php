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

class Index extends Contraint
{
    public function __construct(string $tableName, string $type, array $fields)
    {
        $this->contraint = $type;
        $needs = array_map(function ($field) use ($tableName) {
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
     * @param  string $type
     * @param  array  $columns
     * @return string
     */
    protected function getIndexName()
    {
        return str_replace(['-', '.'], '_', strtolower($this->getTableName().'_'.implode('_', $this->getAttname()[0]).'_'.$this->contraint));
    }

    public function getField()
    {
        return $this->tableName.'.'.implode('_', $this->getCommand()->getAttname()[0]).'_'.$this->contraint.'+';
    }
}
