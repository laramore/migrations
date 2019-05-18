<?php
/**
 * Correspond to a drop command.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Migrations;

class DropCommand extends Command
{
    protected $meta;
    protected $tableName;
    protected $type;
    protected $attname;
    protected $key;

    public function __construct(string $tableName, string $type, string $attname, string $key, array $properties=[])
    {
        parent::__construct($tableName, $type, $attname, $properties);

        $this->key = $key;
    }

    public function getProperties()
    {
        return array_merge([
            $this->type => $this->key,
        ], $this->properties);
    }

    public function getReverse()
    {
        return null;
    }
}
