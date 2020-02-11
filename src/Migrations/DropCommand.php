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

use Laramore\Interfaces\Migration\IsADropCommand;

class DropCommand extends Command implements IsADropCommand
{
    /**
     * Create a new drop command for a specific key.
     *
     * @param string       $tableName
     * @param string       $type
     * @param string|mixed $attname
     * @param array        $properties
     * @param Command      $reversedCommand
     */
    public function __construct(string $tableName, string $type, $attname, array $properties=[], Command $reversedCommand=null)
    {
        parent::__construct($tableName, $type, $attname, $properties);

        $this->reverse = $reversedCommand;
    }
}
