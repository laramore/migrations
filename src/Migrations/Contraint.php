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
    protected $meta;
    protected $tableNames;
    protected $needs;
    protected $command;

    public function __construct(Meta $meta, string $attname, array $needs, array $properties)
    {
        // $this->meta = $meta;
        $this->needs = $needs;
        $this->command = new Command($meta, $attname, $properties);
        $this->tableNames = array_unique(array_map(function ($need) {
            return $need['table'];
        }, $needs));
    }

    public function getNodes()
    {
        return $this->nodes;
    }

    public function getTableNames()
    {
        return $this->tableNames;
    }

    public function getAttname()
    {
        return $this->attname;
    }

    public function getFields(): array
    {
        $fields = [];

        foreach ($this->needs as $need) {
            $fields[] = $need['table'].'.'.$need['field'];
        }

        return array_unique($fields);
    }
}
