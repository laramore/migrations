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

class Command
{
    protected $meta;
    protected $tableName;
    protected $type;
    protected $attname;
    protected $properties;

    public function __construct(string $tableName, string $type, string $attname, array $properties)
    {
        $this->tableName = $tableName;
        $this->type = $type;
        $this->attname = $attname;
        $this->properties = $properties;
    }

    public function getMeta()
    {
        return $this->meta;
    }

    public function getTableName()
    {
        return $this->tableName;
    }

    public function getAttname()
    {
        return $this->attname;
    }

    public function getProperties()
    {
        return array_merge([
            $this->type => $this->attname,
        ], $this->properties);
    }

    public function setProperty(string $key, $value)
    {
        $this->properties[$key] = $value;

        return $this;
    }

    public function getField()
    {
        return $this->getTableName().'.'.$this->getAttname();
    }
}
