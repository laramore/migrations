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
    protected $oldProperties;

    public function __construct(string $tableName, string $type, string $attname, array $properties, array $oldProperties)
    {
        parent::__construct($tableName, $type, $attname, $properties);

        $this->oldProperties = $oldProperties;
    }

    public function getProperties()
    {
        return array_merge([
            $this->type => $this->attname,
        ], $this->properties, [
            'change' => [],
        ]);
    }

    public function getOldProperties()
    {
        return $this->oldProperties;
    }

    public function getReverse()
    {
        return new ChangeCommand($this->getTableName(), $this->getType(), $this->getAttname(), $this->oldProperties, $this->properties);
    }
}
