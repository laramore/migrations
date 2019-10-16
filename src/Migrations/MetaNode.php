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

use Laramore\Facades\{
    MetaManager, MigrationManager
};
use Laramore\Meta;

class MetaNode extends AbstractNode
{
    protected $type;
    protected $nodes = [];
    protected $constraints = [];
    protected $indexes = [];
    protected $organized = false;
    protected $optimized = false;

    public function __construct(array $nodes=[], string $tableName, string $type='create')
    {
        $this->type = $type;
        $this->tableNames = [$tableName];

        $this->setNodes($nodes);
    }

    protected function setNodes(array $nodes)
    {
        $this->nodes = [];

        $this->addNodes($nodes);
    }

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

    public function getNodes(): array
    {
        return array_merge(
            $this->nodes,
            $this->constraints,
            $this->indexes
        );
    }

    public function getFieldNodes(): array
    {
        return $this->organize()->nodes;
    }

    public function getConstraintNodes(): array
    {
        return $this->organize()->constraints;
    }

    public function getIndexNodes(): array
    {
        return $this->organize()->indexes;
    }

    public function getConstraintCommands(): array
    {
        return array_map(function ($constraint) {
            return $constraint->getCommand();
        }, $this->getConstraintNodes());
    }

    public function getIndexCommands(): array
    {
        return array_map(function ($index) {
            return $index->getCommand();
        }, $this->getIndexNodes());
    }

    public function getFieldReverseCommands(): array
    {
        return array_filter(array_map(function ($constraint) {
            return $constraint->getReverse();
        }, $this->getFieldNodes()));
    }

    public function getConstraintReverseCommands(): array
    {
        return array_filter(array_map(function ($constraint) {
            return $constraint->getReverse();
        }, $this->getConstraintNodes()));
    }

    public function getIndexReverseCommands(): array
    {
        return array_filter(array_map(function ($index) {
            return $index->getReverse();
        }, $this->getIndexNodes()));
    }

    public function getMeta(): Meta
    {
        return MetaManager::getForTableName($this->getTableName());
    }

    public function getTableName(): string
    {
        return $this->tableNames[0];
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type)
    {
        $this->type = $type;
    }

    protected function organizing()
    {
        $nbrOfNodes = count($this->getNodes());

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

    protected function optimizing()
    {
        if ($this->type !== 'delete') {
            $fields = $this->getMeta()->getFields();
            $nbrOfNodes = count($this->getNodes());
            $unorderedNodes = $this->nodes;
            $this->nodes = [];

            foreach ($fields as $field) {
                $attname = $field->getAttname();

                foreach ($unorderedNodes as $node) {
                    if ($node->getAttname() === $attname) {
                        $this->nodes[] = $node;

                        break;
                    }
                }
            }

            if (count($unorderedNodes) !== count($this->nodes)) {
                throw new \Exception('Some commands are not meant to be created by this meta');
            }
        }
    }

    public function getUp()
    {
        switch ($this->getType()) {
            case 'create':
                return [
                    'type' => 'create',
                    'fields' => $this->getFieldNodes(),
                    'constraints' => $this->getConstraintCommands(),
                    'indexes' => $this->getIndexCommands(),
                ];

            case 'delete':
                return [
                    'type' => 'delete',
                    'command' => 'dropIfExists',
                ];

            case 'update':
            default:
                return [
                    'type' => 'update',
                    'fields' => $this->getFieldNodes(),
                    'constraints' => $this->getConstraintCommands(),
                    'indexes' => $this->getIndexCommands(),
                ];
        }
    }

    public function getDown()
    {
        switch ($this->getType()) {
            case 'create':
                return [
                    'type' => 'create',
                    'command' => 'dropIfExists',
                ];

            case 'delete':
                foreach (MigrationManager::getActualNode()->getNodes() as $metaNode) {
                    if ($metaNode->getTableName() === $this->getTableName()) {
                        return [
                            'type' => 'delete',
                            'fields' => $this->getFieldNodes(),
                            'constraints' => $this->getConstraintCommands(),
                            'indexes' => $this->getIndexCommands(),
                        ];
                    }
                }
                throw new \Exception('Unexpected error');

            case 'update':
            default:
                return [
                    'type' => 'update',
                    'fields' => $this->getFieldReverseCommands(),
                    'constraints' => $this->getConstraintReverseCommands(),
                    'indexes' => $this->getIndexReverseCommands(),
                ];
        }
    }
}
