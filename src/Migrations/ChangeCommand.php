<?php
/**
 * Correspond to a change command.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Migrations;

class ChangeCommand extends Command
{
    /**
     * All reversed properties for a specific command/field.
     *
     * @var array
     */
    protected $reversedProperties;

    /**
     * Build a change command object.
     *
     * @param string $tableName
     * @param string $type
     * @param string $attname
     * @param array  $properties
     * @param array  $reversedProperties
     */
    public function __construct(string $tableName, string $type, string $attname, array $properties, array $reversedProperties)
    {
        parent::__construct($tableName, $type, $attname, $properties);

        $this->reversedProperties = $reversedProperties;
    }

    /**
     * Return the command properties.
     *
     * @return array
     */
    public function getProperties(): array
    {
        return array_merge(parent::getProperties(), [
            'change' => [],
        ]);
    }

    /**
     * Return the reversed command properties.
     *
     * @return array
     */
    public function getReversedProperties(): array
    {
        return $this->reversedProperties;
    }

    /**
     * Generate a new reversed command.
     *
     * @return AbstractCommand
     */
    protected function generateReverse(): AbstractCommand
    {
        return new ChangeCommand($this->getTableName(), $this->getType(), $this->getAttname(),
            $this->getReversedProperties(), parent::getProperties());
    }
}
