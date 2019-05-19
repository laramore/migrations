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
    protected $key;

    public function __construct(string $tableName, string $type, $attname, string $key, Command $reversedCommand)
    {
        parent::__construct($tableName, $type, $attname, []);

        $this->key = $key;
        $this->reverse = $reversedCommand;
    }

    public function getProperties()
    {
        return array_merge([
            $this->type => $this->key,
        ], $this->properties);
    }
}
