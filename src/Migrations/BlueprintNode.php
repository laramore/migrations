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

use Laramore\Database\Schema\Builder;
use Illuminate\Support\Fluent;
use Illuminate\Database\Schema\{
    Blueprint, ColumnDefinition
};
use Laramore\Facades\{
    MetaManager, TypeManager
};

class BlueprintNode extends MetaNode
{
    public function __construct(Blueprint $blueprint)
    {
        $nodes = array_merge($blueprint->getColumns(), $commands = array_filter($blueprint->getCommands(), function (Fluent $command) {
            return $command->name !== 'create';
        }));
        $meta = MetaManager::getMetaForTableName($blueprint->getTable());
        parent::__construct($nodes, $meta, count($commands) === count($blueprint->getCommands()) ? 'update' : 'create');
    }

    protected function setNodes(array $nodes)
    {
        $this->nodes = array_map(function ($node) {
            if ($node instanceof ColumnDefinition) {
                return $this->columnToCommand($node);
            } else if ($node instanceof Fluent) {
                return $this->commandToContraint($node);
            }
        }, $nodes);
    }

    protected function cleanUnrelevantAttributes(Fluent $column)
    {
        foreach ($column->getAttributes() as $key => $value) {
            if (is_null($value) || $value === false) {
                unset($column->$key);
            }
        }

        if ($column->precision === 0) {
            unset($column->precision);
        }
    }

    protected function popTypeFromColumn(ColumnDefinition $column)
    {
        $type = $column->type;
        unset($column->type);

        if ($type === TypeManager::integer()->migration) {
            if ($column->unsigned) {
                unset($column->unsigned);

                $type = TypeManager::unsignedInteger()->migration;
            }

            if ($column->autoIncrement) {
                unset($column->autoIncrement);

                $type = TypeManager::increment()->migration;
            }
        }

        return $type;
    }

    protected function popFromColumn(Fluent $column, string $name)
    {
        $value = $column->$name;
        unset($column->$name);

        return $value;
    }

    protected function getNeedsForCommand(Fluent $command)
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

    public function columnToCommand(ColumnDefinition $column)
    {
        $this->cleanUnrelevantAttributes($column);

        $type = $this->popTypeFromColumn($column);
        $attname = $this->popFromColumn($column, 'name');

        return new Command($this->getTableName(), $type, $attname, $column->getAttributes());
    }

    public function commandToContraint(Fluent $command)
    {
        $this->cleanUnrelevantAttributes($command);

        $type = $this->popFromColumn($command, 'name');
        $index = $this->popFromColumn($command, 'index');

        if ($type === 'foreign') {
            $needs = $this->getNeedsForCommand($command);
            $column = $this->popFromColumn($command, 'columns')[0];

            $contraint = new Contraint($this->getTableName(), $column, $needs, $command->getAttributes());
        } else {
            $contraint = new Index($this->getTableName(), $type, $command->columns);
        }

        if ($contraint->getIndexName() !== $index) {
            $contraint->getCommand()->setProperty('index', $index);
        }

        return $contraint;
    }
}
