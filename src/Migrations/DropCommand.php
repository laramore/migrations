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
    /**
     * Create a new drop command for a specific key.
     *
     * @param string       $tableName
     * @param string|mixed $attname
     * @param Command      $reversedCommand
     */
    public function __construct(string $tableName, $attname, Command $reversedCommand=null)
    {
        parent::__construct($tableName, 'dropColumn', $attname, []);

        $this->reverse = $reversedCommand;
    }
}
