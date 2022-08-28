<?php
/**
 * Correspond to a migration constraint.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Migrations;

class Constraint extends AbstractCommand
{
    /**
     * The command is defined for this table.
     *
     * @var string
     */
    protected $tableName;

    /**
     * The constraint name.
     *
     * @var string
     */
    protected $name;

    /**
     * Required fields.
     *
     * @var array
     */
    protected $needs;

    /**
     * Command to run when this constraint is satisfied.
     *
     * @var Command
     */
    protected $command;

    /**
     * Class to generate a valid command.
     *
     * @var string
     */
    protected $commandClass = Command::class;

    /**
     * Type of the constraint.
     *
     * @var string
     */
    protected $constraint = 'foreign';

    /**
     * Create a new constraint for a specific field, with requirements and properties attached to this constraint.
     *
     * @param string       $tableName
     * @param string|array $attname
     * @param array        $needs
     * @param array        $properties
     */
    public function __construct(string $tableName, $attname, array $needs, array $properties, string $name = null)
    {
        $this->tableName = $tableName;
        $this->needs = $needs;
        $this->name = $name;
        $this->attname = $attname;

        $this->command = new $this->commandClass($tableName, $this->constraint, $attname, array_merge(
            ['name' => $this->getIndexName()],
            $properties
        ));
    }

    /**
     * Return the table name.
     *
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * Return the attribute name
     *
     * @return string|array
     */
    public function getAttname()
    {
        return $this->attname;
    }

    /**
     * Return the associated command.
     *
     * @return Command
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Return the properties for migration generation.
     *
     * @return array
     */
    public function getMigrationProperties(): array
    {
        return $this->getCommand()->getMigrationProperties();
    }

    /**
     * Return all requirements for this constraint.
     *
     * @return array
     */
    public function getNeeds(): array
    {
        return $this->needs;
    }

    /**
     * Return a distinct field formats.
     *
     * @return array
     */
    public function getFields(): array
    {
        $fields = [];

        foreach ($this->getNeeds() as $need) {
            $fields[] = $need['table'].'.'.$need['field'];
        }

        return \array_unique($fields);
    }

    /**
     * Return a distinct constraint field format.
     *
     * @return string
     */
    public function getField(): string
    {
        return $this->getCommand()->getField().'*';
    }

    /**
     * Create a default index name for the table.
     *
     * @return string
     */
    public function getIndexName(): string
    {
        return $this->name ?: \str_replace(['-', '.'], '_', \strtolower($this->getTableName().'_'.$this->getAttname().'_'.$this->constraint));
    }

    /**
     * Generate a new reversed command.
     *
     * @return AbstractCommand
     */
    protected function generateReverse(): AbstractCommand
    {
        return new DropConstraint($this->getTableName(), $this->getIndexName(), $this);
    }
}
