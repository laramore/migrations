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
    protected $optimized = false;

    public function __construct(array $nodes=[])
    {
        $this->setNodes($nodes);
    }

    protected function loadTableNames($nodes)
    {
        if ($nodes instanceof Command || $nodes instanceof Contraint) {
            return [$nodes->getTableName()];
        } else if ($nodes instanceof Node) {
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
        $nodes = array_values($nodes);

        $this->organized = false;
        $this->optimized = false;

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
                $fields[] = $node->getField();
            }
        }

        return array_unique($fields);
    }

    protected function removeNode(int $index)
    {
        unset($this->nodes[$index]);

        $this->nodes = array_values($this->nodes);
    }

    protected function insertNodes(array $nodes, int $index)
    {
        $this->nodes = array_values(array_merge(
            array_slice($this->nodes, 0, $index),
            $nodes,
            array_slice($this->nodes, $index),
        ));
    }

    protected function moveASliceOfNodes(int $firstIndex, int $lastIndex, int $newIndex)
    {
        for ($firstIndex; $firstIndex <= $lastIndex; $firstIndex++ && $newIndex++) {
            $element = array_splice($this->nodes, $firstIndex, 1);

            array_splice($this->nodes, $newIndex, 0, $element);
        }

        $this->nodes = array_values($this->nodes);
    }

    protected function flat()
    {
        $nbrOfNodes = count($this->getNodes());

        for ($i = 0; $i < $nbrOfNodes; $i++) {
            $node = $this->getNodes()[$i];

            if ($node instanceof Node) {
                $subNodes = $node->organize()->getNodes();
                $nbrOfNodes += (count($subNodes) - 1);
                $this->removeNode($i);
                $this->insertNodes($subNodes, $i--);
            }
        }
    }

    public function organize()
    {
        if ($this->organized) {
            return $this;
        }

        $this->flat();

        $fields = [];
        $nbrOfNodes = count($this->getNodes());

        for ($i = 0; $i < $nbrOfNodes; $i++) {
            $node = $this->getNodes()[$i];

            if ($node instanceof Contraint) {
                $neededFields = $node->getFields();
                $missingFields = array_diff($neededFields, $fields);

                if (count($missingFields)) {
                    // We only treat one missing field per turn.
                    $missingField = $missingFields[0];
                    [$missingTable, $missingAttname] = explode('.', $missingField);

                    $firstIndex = null;
                    $lastIndex = null;
                    $movingTable = null;

                    for ($j = ($i + 1); $j < $nbrOfNodes; $j++) {
                        $nodeToMove = $this->getNodes()[$j];

                        if (is_null($firstIndex)) {
                            if ($nodeToMove instanceof Command) {
                                if ($nodeToMove->getTableName() === $missingTable && $nodeToMove->getAttname() === $missingAttname) {
                                    $firstIndex = $lastIndex = $j;
                                    $movingTable = $missingTable;
                                }
                            } else if (in_array($missingField, $nodeToMove->getFields())) {
                                $firstIndex = $lastIndex = $j;
                                $movingTable = $nodeToMove->getTableName();
                            }
                        } else if ($nodeToMove->getTableName() === $movingTable) {
                            $lastIndex = $j;
                        }
                    }

                    if (is_null($firstIndex)) {
                        throw new \Exception('Unexepected error: a required field is not defined');
                    }

                    $this->moveASliceOfNodes($firstIndex, $lastIndex, $i);

                    $i = 0;
                }
            } else {
                $fields[] = $node->getField();
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

        for ($i = ($nbrOfNodes - 1); $i > 0; $i--) {
            $node = $this->getNodes()[$i];
            $movedNodes = [];

            if ($node instanceof Contraint) {
                $movingTable = $node->getTableName();

                for ($j = 0; $j <= $i; $j++) {
                    $nodeToMove = $this->getNodes()[$j];
                    $moved = false;

                    if (in_array($nodeToMove, $movedNodes)) {
                        continue;
                    }

                    if ($nodeToMove instanceof Command) {
                        if ($nodeToMove->getTableName() === $movingTable) {
                            for ($k = ($j + 1); $k <= $i; $k++) {
                                $nodeToCheck = $this->getNodes()[$k];

                                if ($nodeToCheck instanceof Contraint) {
                                    if (in_array($nodeToMove->getField(), $nodeToCheck->getFields())) {
                                        $this->moveASliceOfNodes($j, $j, ($k - 1));
                                        $moved = true;
                                        $j--;
                                        break;
                                    }
                                }
                            }

                            if (!$moved && $nodeToMove === $this->getNodes()[$j]) {
                                // Move after the contraint as it is not applied to this node.
                                $this->moveASliceOfNodes($j, $j, $i);
                                $j--;
                            }

                            $movedNodes[] = $nodeToMove;
                        }
                    }
                }
            }
        }

        for ($i = 0; $i < $nbrOfNodes; $i++) {
            $node = $this->getNodes()[$i];
            $movedNodes = [];

            if ($node instanceof Command) {
                $movingTable = $node->getTableName();

                for ($j = 0; $j <= $i; $j++) {
                    $nodeToMove = $this->getNodes()[$j];
                    $moved = false;

                    if (in_array($nodeToMove, $movedNodes)) {
                        continue;
                    }

                    if ($nodeToMove instanceof Command) {
                        if ($nodeToMove->getTableName() === $movingTable) {
                            for ($k = ($j + 1); $k <= $i; $k++) {
                                $nodeToCheck = $this->getNodes()[$k];

                                if ($nodeToCheck instanceof Contraint) {
                                    if (in_array($nodeToMove->getField(), $nodeToCheck->getFields())) {
                                        $this->moveASliceOfNodes($j, $j, ($k - 1));
                                        $moved = true;
                                        $j--;
                                        break;
                                    }
                                }
                            }

                            if (!$moved && $nodeToMove === $this->getNodes()[$j]) {
                                // Move after the contraint as it is not applied to this node.
                                $this->moveASliceOfNodes($j, $j, $i);
                                $j--;
                            }

                            $movedNodes[] = $nodeToMove;
                        }
                    }
                }
            }
        }

        // Ici vérifier qu'on respecte bien les conditions entre $j et $i pour le remonter sur $i
        $this->optimized = true;

        return $this;
    }
}
