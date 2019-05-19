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

    public function getField()
    {
        return $this->tableName.'.'.implode('_', $this->getCommand()->getAttname()[0]).'_'.$this->contraint.'+';
    }
}
