<?php
/**
 * Correspond to a migration node of a specific meta.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Migrations;

use Laramore\Meta;
use Laramore\Facades\{
    Meta as MetaManager, Migrations
};
use Laramore\Interfaces\Migration\IsADropCommand;

class MetaNode extends AbstractNode
{
    /**
     * Define the type of this meta node: create, update or delete.
     *
     * @var string
     */
    protected $type;

    /**
     * All constraint nodes are grouped here after organization.
     *
     * @var array
     */
    protected $constraints = [];

    /**
     * All index nodes are grouped here after organization.
     *
     * @var array
     */
    protected $indexes = [];

    /**
     * Create a meta node with sub nodes specific to a table.
     *
     * @param array  $nodes
     * @param string $tableName
     * @param string $type
     */
    public function __construct(array $nodes, string $tableName, string $type='create')
    {
        $this->type = $type;
        $this->tableNames = [$tableName];

        $this->setNodes($nodes);
    }

    /**
     * Define the sub nodes/commands.
     *
     * @param array $nodes
     * @return void
     */
    protected function setNodes(array $nodes)
    {
        $this->nodes = [];

        $this->addNodes($nodes);
    }

    /**
     * Add sub nodes and force this node to be unoptimized and unorganized.
     *
     * @param array $nodes
     * @return void
     */
    public function addNodes(array $nodes)
    {
        $nodes = \array_map(function ($node) {
            if ($node instanceof AbstractNode) {
                throw new \Exception('A MetaNode only contains commands and constraints');
            } else if ($node->getTableName() !== $this->getTableName()) {
                throw new \Exception('All subnodes should be from the current table name');
            }

            return $node;
        }, \array_values($nodes));

        $this->organized = false;
        $this->optimized = false;

        $this->nodes = \array_merge($this->nodes, $nodes);
    }

    /**
     * Return all the sub commands, constraints and indexes.
     *
     * @return array
     */
    public function getNodes(): array
    {
        return \array_merge(
            parent::getNodes(),
            $this->constraints,
            $this->indexes
        );
    }

    /**
     * Return only the sub nodes/commands.
     *
     * @return array
     */
    public function getFieldNodes(): array
    {
        return $this->organize()->nodes;
    }

    /**
     * Return only the sub constraints.
     *
     * @return array
     */
    public function getConstraintNodes(): array
    {
        return $this->organize()->constraints;
    }

    /**
     * Return only the sub indexes.
     *
     * @return array
     */
    public function getIndexNodes(): array
    {
        return $this->organize()->indexes;
    }

    /**
     * Return all commands by block.
     *
     * @return array
     */
    public function getCommands(): array
    {
        $fieldCommands = $this->getFieldNodes();
        $constraintCommands = \array_map(function ($constraint) {
            return $constraint->getCommand();
        }, $this->getConstraintNodes());
        $indexCommands = \array_map(function ($index) {
            return $index->getCommand();
        }, $this->getIndexNodes());

        $commands = [
            'drop_indexes' => [],
            'drop_constraints' => [],
            'drop_fields' => [],
            'fields' => [],
            'constraints' => [],
            'indexes' => [],
        ];

        foreach ($fieldCommands as $command) {
            $commands[($command instanceof IsADropCommand ? 'drop_fields' : 'fields')][] = $command;
        }

        foreach ($constraintCommands as $command) {
            $commands[($command instanceof IsADropCommand ? 'drop_constraints' : 'constraints')][] = $command;
        }

        foreach ($indexCommands as $command) {
            $commands[($command instanceof IsADropCommand ? 'drop_indexes' : 'indexes')][] = $command;
        }

        // Avoid duplications and empty values.
        foreach ($commands as $key => $value) {
            if (\count($value) > 0) {
                $commands[$key] = \array_filter($value);
            } else {
                unset($commands[$key]);
            }
        }

        return $commands;
    }

    /**
     * Return all reversed commands by block.
     *
     * @return array
     */
    public function getReverseCommands(): array
    {
        $fieldCommands = \array_map(function ($fieldCommand) {
            return $fieldCommand->getReverse();
        }, $this->getFieldNodes());
        $constraintCommands = \array_map(function ($constraint) {
                return $constraint->getCommand()->getReverse();
        }, $this->getConstraintNodes());
        $indexCommands = \array_map(function ($index) {
                return $index->getCommand()->getReverse();
        }, $this->getIndexNodes());

        $commands = [
            'drop_indexes' => [],
            'drop_constraints' => [],
            'drop_fields' => [],
            'fields' => [],
            'constraints' => [],
            'indexes' => [],
        ];

        foreach ($fieldCommands as $command) {
            $commands[($command instanceof IsADropCommand ? 'drop_fields' : 'fields')][] = $command;
        }

        foreach ($constraintCommands as $command) {
            $commands[($command instanceof IsADropCommand ? 'drop_constraints' : 'constraints')][] = $command;
        }

        foreach ($indexCommands as $command) {
            $commands[($command instanceof IsADropCommand ? 'drop_indexes' : 'indexes')][] = $command;
        }

        // Avoid duplications and empty values.
        foreach ($commands as $key => $value) {
            if (\count($value) > 0) {
                $commands[$key] = \array_filter($value);
            } else {
                unset($commands[$key]);
            }
        }

        return $commands;
    }

    /**
     * Return the meta managing this table.
     *
     * @return Meta
     */
    public function getMeta(): Meta
    {
        return MetaManager::getForTableName($this->getTableName());
    }

    /**
     * Return the table used for this meta.
     *
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableNames[0];
    }

    /**
     * Return the type of this meta node.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * This method is called when the node is asked to be organized.
     *
     * @return void
     */
    protected function organizing()
    {
        $nbrOfNodes = \count($this->getNodes());

        for ($i = 0; $i < $nbrOfNodes; $i++) {
            $node = $this->getNodes()[$i];

            if ($node instanceof Constraint) {
                if ($node instanceof Index) {
                    $this->indexes[] = $node;
                } else {
                    $this->constraints[] = $node;
                }

                $this->removeNode($i--);
                $nbrOfNodes--;
            }
        }
    }

    /**
     * This method is called when the node is asked to be optimized.
     *
     * @return void
     */
    protected function optimizing()
    {
        if ($this->type !== 'delete') {
            $fields = $this->getMeta()->getAttributes();
            $nbrOfNodes = \count($this->getNodes());
            $unorderedNodes = $this->nodes;
            $this->nodes = [];

            foreach ($fields as $field) {
                $attname = $field->getAttname();

                foreach ($unorderedNodes as $key => $node) {
                    if ($node->getAttname() === $attname) {
                        $this->nodes[] = $node;
                        unset($unorderedNodes[$key]);

                        break;
                    }
                }
            }

            $this->nodes = \array_merge($this->nodes, $unorderedNodes);
        }
    }

    /**
     * Generate the up migration for this meta node.
     *
     * @return array
     */
    public function getUp(): array
    {
        switch ($type = $this->getType()) {
            case 'delete':
                return [
                    'type' => 'delete',
                    'line' => 'dropIfExists',
                ];

            case 'create':
            case 'update':
            default:
                return [
                    'type' => $type,
                    'blocks' => $this->getCommands(),
                ];
        }
    }

    /**
     * Generate the down migration for this meta node.
     *
     * @return array
     */
    public function getDown()
    {
        switch ($type = $this->getType()) {
            case 'create':
                return [
                    'type' => 'create',
                    'line' => 'dropIfExists',
                ];

            case 'delete':
                foreach (Migrations::getActualNode()->getNodes() as $metaNode) {
                    if ($metaNode->getTableName() === $this->getTableName()) {
                        return [
                            'type' => 'delete',
                            'blocks' => $this->getCommands(),
                        ];
                    }
                }
                throw new \Exception('It looks like we are generating a delete migration for an inexistant table.');

            case 'update':
            default:
                return [
                    'type' => $type,
                    'blocks' => $this->getReverseCommands(),
                ];
        }
    }
}
