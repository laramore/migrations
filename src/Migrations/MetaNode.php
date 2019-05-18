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
    protected $contraints = [];
    protected $organized = false;
    protected $optimized = false;

    public function __construct(array $nodes=[], Meta $meta, string $type='create')
    {
        $this->tableNames = [$meta->getTableName()];
        $this->type = $type;

        $this->setNodes($nodes);
    }

    protected function setNodes(array $nodes)
    {
        $nodes = array_map(function ($node) {
            if ($node instanceof AbstractNode) {
                throw new \Exception('A MetaNode only contains commands and contraints');
            } else if ($node->getTableName() !== $this->getTableName()) {
                throw new \Exception('All subnodes should be from the current table name');
            }

            return $node;
        }, array_values($nodes));

        $this->organized = false;
        $this->optimized = false;

        $this->nodes = $nodes;
    }

    public function getNodes(): array
    {
        return array_merge(
            $this->nodes,
            $this->contraints
        );
    }

    public function getFieldNodes(): array
    {
        return $this->organize()->nodes;
    }

    public function getContraintNodes(): array
    {
        return $this->organize()->contraints;
    }

    public function getContraintCommands(): array
    {
        return array_map(function ($contraint) {
            return $contraint->getCommand();
        }, $this->getContraintNodes());
    }

    public function getFieldReverseCommands(): array
    {
        return array_filter(array_map(function ($contraint) {
            return $contraint->getReverse();
        }, $this->getFieldNodes()));
    }

    public function getContraintReverseCommands(): array
    {
        return array_filter(array_map(function ($contraint) {
            return $contraint->getReverse();
        }, $this->getContraintNodes()));
    }

    public function getMeta(): Meta
    {
        return MetaManager::getMetaFromTableName($this->getTableName());
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

            if ($node instanceof Contraint) {
                $this->contraints[] = $node;
                $this->removeNode($i--);
                $nbrOfNodes--;
            }
        }
    }

    protected function optimizing()
    {
        $fields = $this->getMeta()->getFields();
        $nbrOfNodes = count($this->getNodes());
        $unorderedNodes = $this->nodes;
        $this->nodes = [];

        foreach ($unorderedNodes as $node) {
            $attname = $node->getAttname();

            foreach ($fields as $field) {
                if ($field->attname === $attname) {
                    $this->nodes[] = $node;

                    break;
                }
            }
        }

        if (count($unorderedNodes) !== count($this->nodes)) {
            throw new \Exception('Some commands are not meant to be created by this meta');
        }
    }

    public function getUp()
    {
        switch ($this->getType()) {
            case 'create':
                return [
                    'type' => 'create',
                    'fields' => $this->getFieldNodes(),
                    'contraints' => $this->getContraintCommands(),
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
                    'contraints' => $this->getContraintCommands(),
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
                foreach (MigrationManager::getWantedNode()->getNodes() as $metaNode) {
                    if ($metaNode->getTableName() === $this->getTableName()) {
                        return [
                            'type' => 'delete',
                            'fields' => $metaNode->getFieldReverseNodes(),
                            'contraints' => $metaNode->getContraintReverseCommands(),
                        ];
                    }
                }
                throw new \Exception('Unexpected error');

            case 'update':
            default:
                return [
                    'type' => 'update',
                    'fields' => $this->getFieldReverseCommands(),
                    'contraints' => $this->getContraintReverseCommands(),
                ];
        }
    }
}
