<?php
/**
 * Correspond to a migration node from the Laravel Blueprint.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Migrations;

use Illuminate\Support\{
    Fluent, Str
};
use Illuminate\Database\Schema\{
    Blueprint, ColumnDefinition
};
use Laramore\Facades\Meta;

class BlueprintNode extends MetaNode
{
    protected $type = 'update';

    /**
     * Create a node based on a blueprint definition.
     *
     * @param Blueprint $blueprint
     */
    public function __construct(Blueprint $blueprint)
    {
        $this->tableNames = [$blueprint->getTable()];

        $filtered = \array_filter($blueprint->getCommands(), function (Fluent $command) {
            return $command->name !== 'create';
        });
        $constraints = \array_map([$this, 'commandToConstraint'], $filtered);
        $commands = \array_map([$this, 'columnToCommand'], $blueprint->getColumns());

        $this->setNodes(\array_merge($commands, $constraints));
    }

    /**
     * This method is called when the node is asked to be optimized.
     * Only optimize if a meta exists for this table.
     *
     * @return void
     */
    protected function optimizing()
    {
        if (Meta::hasForTableName($this->getTableName())) {
            parent::optimizing();
        } else {
            $this->unpack();
        }
    }

    /**
     * Remove from fluent command all unrelevant attributes.
     *
     * @param  Fluent $command
     * @return void
     */
    protected function cleanUnrelevantAttributes(Fluent $command)
    {
        foreach ($command->getAttributes() as $key => $value) {
            if (\is_null($value) || ($value === false && $key !== 'default')) {
                unset($command->$key);
            }
        }

        if ($command->precision === 0) {
            unset($command->precision);
        }
    }

    /**
     * Pop a definition from a column.
     *
     * @param  Fluent $column
     * @param  string $name
     * @return mixed
     */
    protected function popFromColumn(Fluent $column, string $name)
    {
        $value = $column->$name;
        unset($column->$name);

        return $value;
    }

    /**
     * Pop the type definition from a column definition.
     *
     * @param  ColumnDefinition|Fluent $column
     * @return string
     */
    protected function popTypeFromColumn(Fluent $column): string
    {
        $type = $this->popFromColumn($column, 'type');

        // Here, if our field is an integer, we need to handle unsigned and increment integers.
        if ($type === 'integer') {
            if ($column->unsigned) {
                unset($column->unsigned);

                $type = 'unsigned_integer';
            }

            if ($column->autoIncrement) {
                unset($column->autoIncrement);

                $type = 'increments';
            }
        }

        if ($type === 'dateTime') { # Fix case.
            return 'datetime';
        }

        return $type;
    }

    /**
     * Return all requirements for a fluent command.
     *
     * @param  Fluent $command
     * @return array
     */
    protected function getNeedsForCommand(Fluent $command): array
    {
        return [
            [
                'table' => $this->getTableName(),
                'field' => $command->columns[0],
            ],
            [
                'table' => $command->on,
                'field' => $command->references,
            ],
        ];
    }

    /**
     * Transform a column definition to a migration command.
     *
     * @param  ColumnDefinition|Fluent $column
     * @return Command
     */
    public function columnToCommand(Fluent $column): Command
    {
        $this->cleanUnrelevantAttributes($column);

        $type = $this->popTypeFromColumn($column);
        $attname = $this->popFromColumn($column, 'name');

        return new Command($this->getTableName(), $type, $attname, $column->getAttributes());
    }

    /**
     * Transform a fluent command to a migration constraint.
     *
     * @param  Fluent $command
     * @return Constraint
     */
    public function commandToConstraint(Fluent $command)
    {
        $this->cleanUnrelevantAttributes($command);

        $type = $this->popFromColumn($command, 'name');
        $index = $this->popFromColumn($command, 'index');

        if (Str::startsWith($type, 'drop')) {
            if ($type === 'dropColumn') {
                $column = $this->popFromColumn($command, 'columns')[0];

                return new DropCommand($this->getTableName(), $type, $column);
            } else if ($type === 'dropForeign') {
                return new DropConstraint($this->getTableName(), $index);
            } else {
                return new DropIndex($this->getTableName(), $type, $index);
            }
        } else if ($type === 'foreign') {
            $needs = $this->getNeedsForCommand($command);
            $column = $this->popFromColumn($command, 'columns')[0];

            $constraint = new Constraint($this->getTableName(), $column, $needs, $command->getAttributes());
        } else {
            $constraint = new Index($this->getTableName(), $type, $command->columns);
        }

        // If the user defines itself the index name, it is required to put it.
        if ($constraint->getIndexName() !== $index) {
            $constraint->getCommand()->setProperty('index', $index);
        }

        return $constraint;
    }
}
