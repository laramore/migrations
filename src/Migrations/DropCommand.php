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
     * The foreign key to drop.
     *
     * @var string
     */
    protected $key;

    /**
     * Create a new drop command for a specific key.
     *
     * @param string       $tableName
     * @param string       $type
     * @param string|mixed $attname
     * @param string       $key
     * @param Command      $reversedCommand
     */
    public function __construct(string $tableName, string $type, $attname, string $key, Command $reversedCommand)
    {
        parent::__construct($tableName, $type, $attname, []);

        $this->key = $key;
        $this->reverse = $reversedCommand;
    }

    /**
     * Return the command properties.
     *
     * @return array
     */
    public function getProperties(): array
    {
        return array_merge([
            $this->type => $this->key,
        ], $this->properties);
    }
}
