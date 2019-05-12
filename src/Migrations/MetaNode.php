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

class MetaNode extends Node
{
    protected $type;
    protected $nodes = [];
    protected $contraints = [];
    protected $tableNames;
    protected $organized = false;
    protected $optimized = false;

    public function __construct(array $nodes=[], Meta $meta, string $type='create')
    {
        $this->tableNames = [$meta->getTableName()];
        $this->tableMetas[$this->getTableName()] = $meta;
        $this->type = $type;

        $this->setNodes($nodes);
    }

    protected function setNodes($nodes)
    {
        $nodes = array_map(function ($node) {
            if ($node instanceof Node) {
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

    public function getFieldNodes(): array
    {
        return $this->nodes;
    }

    public function getContraintNodes(): array
    {
        return $this->contraints;
    }

    public function getMeta(): Meta
    {
        return $this->tableMetas[$this->getTableName()];
    }

    public function getTableName(): string
    {
        return $this->tableNames[0];
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function organize()
    {
        if ($this->organized) {
            return $this;
        }

        $nbrOfNodes = count($this->getNodes());

        for ($i = 0; $i < $nbrOfNodes; $i++) {
            $node = $this->getNodes()[$i];

            if ($node instanceof Contraint) {
                $this->contraints[] = $node;
                $this->removeNode($i--);
                $nbrOfNodes--;
            }
        }

        $this->organized = true;

        return $this;
    }

    public function optimize()
    {
        if (!$this->organized) {
            throw new \Exception('This migration needs to be organized first !');
        }

        if ($this->optimized) {
            return $this;
        }

        $nbrOfNodes = count($this->getNodes());
        $unorderedNodes = $this->nodes;
        $this->nodes = [];

        foreach ($this->getMeta()->getFields() as $field) {
            $attname = $field->attname;

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

        $this->optimized = true;

        return $this;
    }
}
