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
    protected $tableMetas = [];
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

        $this->nodes = $nodes;

        $this->tableNames = array_values($this->loadTableNames($nodes));
    }

    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function getTableNames(): array
    {
        return $this->tableNames;
    }

    public function getTableMetas(): array
    {
        return $this->tableMetas;
    }

    public function getFields(): array
    {
        $fields = [];

        foreach ($this->getNodes() as $node) {
            if ($node instanceof Node) {
                $fields = array_merge($fields, $node->getFields());
            } else if ($node instanceof Command) {
                $fields[] = $node->getField();
            }
        }

        return array_unique($fields);
    }

    public function getFieldsAndContraints(): array
    {
        $fields = [];
        foreach ($this->getNodes() as $node) {
            if ($node instanceof Command) {
                $fields[] = $node->getField();
            } else if ($node instanceof Contraint) {
                $fields[] = $node->getCommand()->getField().'+';
            } else {
                $fields = array_merge($fields, $node->getFieldsAndContraints());
            }
        }

        return array_unique($fields);
    }

    protected function removeNode(int $index)
    {
        unset($this->nodes[$index]);

        $this->nodes = array_values($this->nodes);
    }

    protected function removeNodes(int $firstIndex, int $lastIndex)
    {
        array_splice($this->nodes, $firstIndex, ($lastIndex - $firstIndex));

        $this->nodes = array_values($this->nodes);
    }

    protected function insertNode(Node $node, int $index)
    {
        $this->insertNodes([$node], $index);
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

    protected function moveANode(int $currentIndex, int $newIndex)
    {
        $this->moveASliceOfNodes($currentIndex, $currentIndex, $newIndex);
    }

    protected function unpack()
    {
        $nbrOfNodes = count($this->getNodes());

        for ($i = 0; $i < $nbrOfNodes; $i++) {
            $node = $this->getNodes()[$i];

            if ($node instanceof Node) {
                if ($node instanceof MetaNode) {
                    $this->tableMetas[$node->getTableName()] = $node->getMeta();
                }

                $subNodes = $node->organize()->optimize()->getNodes();
                $nbrOfNodes += (count($subNodes) - 1);
                $this->removeNode($i);
                $this->insertNodes($subNodes, $i--);
            }
        }
    }

    protected function pack()
    {
        $nbrOfNodes = count($this->getNodes());

        $firstIndex = null;
        $commonTable = null;

        for ($i = 0; $i < $nbrOfNodes; $i++) {
            $node = $this->getNodes()[$i];

            if (is_null($firstIndex)) {
                $firstIndex = $i;
                $commonTable = $node->getTableName();
            } else if ($node->getTableName() !== $commonTable) {
                $lastIndex = $i;
                $subNodes = array_slice($this->nodes, $firstIndex, ($lastIndex - $firstIndex));
                $packNode = new MetaNode($subNodes, $this->tableMetas[$commonTable]);

                // Do not handle the just created node.
                $i -= (count($subNodes) - 1);
                $nbrOfNodes -= (count($subNodes) - 1);

                $this->removeNodes($firstIndex, $lastIndex);
                $this->insertNode($packNode->organize()->optimize(), $firstIndex);

                $firstIndex = $i;
                $commonTable = $node->getTableName();
            }
        }

        if (!is_null($firstIndex) && $firstIndex !== 0) {
            $subNodes = array_slice($this->nodes, $firstIndex, ($nbrOfNodes - $firstIndex));
            $packNode = new MetaNode($subNodes, $this->tableMetas[$commonTable]);

            $this->removeNodes($firstIndex, $nbrOfNodes);
            $this->insertNode($packNode->organize()->optimize(), $firstIndex);
        }
    }

    protected function contraintCanMove(Contraint $node, int $firstIndex, int $lastIndex)
    {
        for ($i = $firstIndex; $i < $lastIndex; $i++) {
            $nodeToCheck = $this->getNodes()[$i];

            if ($nodeToCheck instanceof Command && in_array($nodeToCheck->getField(), $node->getFields())) {
                return false;
            }
        }

        return true;
    }

    public function organize()
    {
        if ($this->organized) {
            return $this;
        }

        $this->unpack();

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
                        continue;
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
                                        $this->moveANode($j, ($k - 1));
                                        $moved = true;
                                        $j--;
                                        break;
                                    }
                                }
                            }

                            if (!$moved && $nodeToMove === $this->getNodes()[$j]) {
                                // Move after the contraint as it is not applied to this node.
                                $this->moveANode($j, $i);
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

                for ($j = ($nbrOfNodes - 1); $j > $i; $j--) {
                    $nodeToMove = $this->getNodes()[$j];

                    if (in_array($nodeToMove, $movedNodes)) {
                        continue;
                    }

                    if ($nodeToMove instanceof Command) {
                        if ($nodeToMove->getTableName() === $movingTable) {
                            $this->moveANode($j, $i);
                            $movedNodes[] = $nodeToMove;
                            $j++;
                        }
                    } else {
                        if ($this->contraintCanMove($nodeToMove, $i, $j)) {
                            $this->moveANode($j, $i);
                            $movedNodes[] = $nodeToMove;
                            $j++;
                        }
                    }
                }
            }
        }

        // Now, we can repack by subnodes all common commands with the same table.
        $this->pack();

        $this->optimized = true;

        return $this;
    }
}
