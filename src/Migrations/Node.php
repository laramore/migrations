<?php
/**
 * Correspond to a migration node.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Migrations;

class Node
{
    protected $nodes = [];
    protected $tableNames = [];
    protected $organized = false;

    public function __construct(array $nodes=[])
    {
        $this->setNodes($nodes);
    }

    protected function loadTableNames($nodes)
    {
        if ($nodes instanceof Command) {
            return [$nodes->getTableName()];
        } else if ($nodes instanceof Node || $nodes instanceof Contraint) {
            return $nodes->getTableNames();
        } else if (is_array($nodes) && count($nodes)) {
            return array_unique(array_merge(...array_map(function ($node) {
                return $this->loadTableNames($node);
            }, $nodes)));
        } else {
            throw new \Exception('A node can only have subnodes and commands');
        }
    }

    protected function setNodes($nodes)
    {
        $this->organized = false;
        $nodes = array_values($nodes);

        if (is_array($nodes) && count($nodes) === 1) {
            $this->nodes = $nodes[0];
        } else {
            $this->nodes = $nodes;
        }

        $this->tableNames = $this->loadTableNames($nodes);
    }

    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function getTableNames(): array
    {
        return $this->tableNames;
    }

    public function getFields(): array
    {
        $fields = [];

        foreach ($this->nodes as $node) {
            if ($node instanceof Node) {
                $fields = array_merge($fields, $node->getFields());
            } else if ($node instanceof Command) {
                $fields[] = $node->getTableName().'.'.$node->getAttname();
            }
        }

        return array_unique($fields);
    }

    public function organize()
    {
        if ($this->organized) {
            return $this;
        }

        $fields = [];
        $nbrOfNodes = count($this->getNodes());

        for ($i = 0; $i < $nbrOfNodes; $i++) {
            $node = $this->getNodes()[$i];

            if ($node instanceof Node) {
                $fields = array_merge($fields, $node->organize()->getFields());
            } else if ($node instanceof Contraint) {
                $neededFields = $node->getFields();
                $missingFields = array_diff($neededFields, $fields);

                if (count($missingFields)) {
                    // We only treat one missing field per turn.
                    $missingField = $missingFields[0];
                    [$missingTable, $missingAttname] = explode('.', $missingField);

                    $firstIndex = null;
                    $lastIndex = null;
                    $movingTables = [];

                    for ($j = ($i + 1); $j < $nbrOfNodes; $j++) {
                        $nodeToMove = $this->getNodes()[$j];

                        if (is_null($firstIndex)) {
                            if ($node instanceof Command) {
                                if ($nodeToMove->getTableName() === $missingTable && $nodeToMove->getAttname() === $missingAttname) {
                                    $firstIndex = $lastIndex = $j;
                                    $movingTables = [$missingTable];
                                }
                            } else if (in_array($missingField, $nodeToMove->getFields())) {
                                $firstIndex = $lastIndex = $j;
                                $movingTables = $nodeToMove->getTableNames();
                            }
                        } else {
                            if ($node instanceof Command) {
                                if (in_array($nodeToMove->getTableName(), $movingTables)) {
                                    $lastIndex = $j;
                                }
                            } else if (count(array_intersect($movingTables, $nodeToMove->getTableNames()))) {
                                $lastIndex = $j;
                            }
                        }
                    }

                    if (is_null($firstIndex)) {
                        throw new \Exception('Unexepected error: a required field is not defined');
                    }

                    for ($firstIndex; $firstIndex <= $lastIndex; $firstIndex++ && $i++) {
                        $element = array_splice($this->nodes, $firstIndex, 1);

                        array_splice($this->nodes, $i, 0, $element);
                    }

                    $i = 0;
                }
            }
        }

        $this->organized = true;

        return $this;
    }
}
