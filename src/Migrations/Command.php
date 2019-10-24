<?php
/**
 * Correspond to a migration command.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Migrations;

class Command extends AbstractCommand
{
    /**
     * The command is defined for this table.
     *
     * @var string
     */
    protected $tableName;

    /**
     * Command type, name.
     *
     * @var string
     */
    protected $type;

    /**
     * Attname(s) on which this command is for.
     *
     * @var string|array
     */
    protected $attname;

    /**
     * All properties for this command.
     *
     * @var array
     */
    protected $properties;

    /**
     * Create a new command, for a specific field in a table.
     *
     * @param string       $tableName
     * @param string       $type
     * @param string|array $attname
     * @param array        $properties
     */
    public function __construct(string $tableName, string $type, $attname, array $properties)
    {
        $this->tableName = $tableName;
        $this->type = $type;
        $this->attname = $attname;
        $this->properties = $properties;
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
     * Return the command type, name.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Return the command properties.
     *
     * @return array
     */
    public function getProperties(): array
    {
        return \array_merge([
            $this->type => $this->attname,
        ], $this->properties);
    }

    /**
     * Return the properties for migration generation.
     *
     * @return array
     */
    public function getMigrationProperties(): array
    {
        $properties = \array_map(function ($value) {
            return [$value];
        }, $this->getProperties());

        // For enum fields, Laravel requires that the property "allowed" is defined.
        if (\in_array($this->type, ['enum', 'set']) && isset($properties['allowed'])) {
            $properties[$this->type][] = $properties['allowed'];

            unset($properties['allowed']);
        }

        return $properties;
    }

    /**
     * Set a new property.
     *
     * @param string $key
     * @param mixed  $value
     * @return self
     */
    public function setProperty(string $key, $value)
    {
        $this->properties[$key] = $value;

        return $this;
    }

    /**
     * Return a distinct field format.
     *
     * @return string
     */
    public function getField(): string
    {
        return $this->getTableName().'.'.implode('_', (array) $this->getAttname());
    }

    /**
     * Generate a new reversed command.
     *
     * @return AbstractCommand
     */
    protected function generateReverse(): AbstractCommand
    {
        return new Command($this->getTableName(), 'dropColumn', $this->getAttname(), []);
    }
}
