<?php
/**
 * Define a general migration node.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Migrations;

abstract class AbstractNode
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
        } else if ($nodes instanceof AbstractNode) {
            return $nodes->getTableNames();
        } else if (is_array($nodes)) {
            return array_unique(array_merge([], ...array_map(function ($node) {
                return $this->loadTableNames($node);
            }, $nodes)));
        } else {
            throw new \Exception('A node can only have subnodes and commands');
        }
    }

    protected function setNodes(array $nodes)
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
            if ($node instanceof AbstractNode) {
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
                $fields[] = $node->getCommand()->getField().'*';
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

    protected function insertNode(AbstractNode $node, int $index)
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

    protected function moveNodes(int $firstIndex, int $lastIndex, int $newIndex)
    {
        for ($firstIndex; $firstIndex <= $lastIndex; $firstIndex++ && $newIndex++) {
            $element = array_splice($this->nodes, $firstIndex, 1);

            array_splice($this->nodes, $newIndex, 0, $element);
        }

        $this->nodes = array_values($this->nodes);
    }

    protected function moveNode(int $currentIndex, int $newIndex)
    {
        $this->moveNodes($currentIndex, $currentIndex, $newIndex);
    }

    public function unpack()
    {
        $nbrOfNodes = count($this->getNodes());

        for ($i = 0; $i < $nbrOfNodes; $i++) {
            $node = $this->getNodes()[$i];

            if ($node instanceof AbstractNode) {
                $subNodes = $node->organize()->optimize()->getNodes();
                $nbrOfNodes += (count($subNodes) - 1);
                $this->removeNode($i);
                $this->insertNodes($subNodes, $i--);
            }
        }

        return $this;
    }

    public function pack()
    {
        $nbrOfNodes = count($this->getNodes());

        $firstIndex = null;
        $commonTable = null;
        $passedTables = [];

        for ($i = 0; $i < $nbrOfNodes; $i++) {
            $node = $this->getNodes()[$i];

            if ($node instanceof AbstractNode) {
                $subNodes = $node->organize()->optimize()->getNodes();
                $nbrOfNodes += (count($subNodes) - 1);
                $this->removeNode($i);
                $this->insertNodes($subNodes, $i--);
            } else {
                if (is_null($firstIndex)) {
                    $firstIndex = $i;
                    $commonTable = $node->getTableName();
                } else if ($node->getTableName() !== $commonTable) {
                    $lastIndex = $i;
                    $subNodes = array_slice($this->nodes, $firstIndex, ($lastIndex - $firstIndex));

                    if (in_array($commonTable, $passedTables)) {
                        $metaType = 'update';
                    } else {
                        $metaType = 'create';
                        $passedTables[] = $commonTable;
                    }

                    $packNode = new MetaNode($subNodes, Manager::getTableMeta($commonTable), $metaType);

                    // Do not handle the just created node.
                    $i -= (count($subNodes) - 1);
                    $nbrOfNodes -= (count($subNodes) - 1);

                    $this->removeNodes($firstIndex, $lastIndex);
                    $this->insertNode($packNode->organize()->optimize(), $firstIndex);

                    $firstIndex = $i;
                    $commonTable = $node->getTableName();
                }
            }
        }

        if (!is_null($firstIndex) && $firstIndex !== 0) {
            $subNodes = array_slice($this->nodes, $firstIndex, ($nbrOfNodes - $firstIndex));

            if (in_array($commonTable, $passedTables)) {
                $metaType = 'update';
            } else {
                $metaType = 'create';
            }

            $packNode = new MetaNode($subNodes, Manager::getTableMeta($commonTable), $metaType);

            $this->removeNodes($firstIndex, $nbrOfNodes);
            $this->insertNode($packNode->organize()->optimize(), $firstIndex);
        }

        return $this;
    }

    abstract protected function organizing();

    public function organize()
    {
        if ($this->organized) {
            return $this;
        }

        $this->organizing();

        $this->organized = true;

        return $this;
    }

    abstract protected function optimizing();

    public function optimize()
    {
        if (!$this->organized) {
            throw new \Exception('This migration needs to be organized first !');
        }

        if ($this->optimized) {
            return $this;
        }

        $this->optimizing();

        $this->optimized = true;

        return $this;
    }
}
