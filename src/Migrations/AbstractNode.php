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

use Metas, Migrations;

abstract class AbstractNode
{
    /**
     * Regroup all sub nodes/commands.
     *
     * @var array
     */
    protected $nodes = [];

    /**
     * All used tables. Usefull for ordering.
     *
     * @var array
     */
    protected $tableNames = [];

    /**
     * Indicate if the node has been organized.
     *
     * @var boolean
     */
    protected $organized = false;

    /**
     * Indicate if the node has been optimized.
     *
     * @var boolean
     */
    protected $optimized = false;

    /**
     * Build a node regrouping sub nodes/commands.
     *
     * @param array $nodes
     */
    public function __construct(array $nodes=[])
    {
        $this->setNodes($nodes);
    }

    /**
     * Find all used tables from nodes.
     *
     * @param  mixed $nodes
     * @return array
     */
    protected function loadTableNames($nodes): array
    {
        if ($nodes instanceof AbstractCommand) {
            return [$nodes->getTableName()];
        } else if ($nodes instanceof AbstractNode) {
            return $nodes->getTableNames();
        } else if (\is_array($nodes)) {
            return \array_unique(
                \array_merge([], ...\array_map(function ($node) {
                    return $this->loadTableNames($node);
                }, $nodes))
            );
        }

        throw new \Exception('A node can only have subnodes and commands');
    }

    /**
     * Define the sub nodes/commands.
     *
     * @param array $nodes
     * @return void
     */
    protected function setNodes(array $nodes)
    {
        $nodes = \array_values($nodes);

        $this->organized = false;
        $this->optimized = false;

        $this->nodes = $nodes;

        $this->tableNames = \array_values($this->loadTableNames($nodes));
    }

    /**
     * Return all the sub nodes/commands.
     *
     * @return array
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * Return the used tables.
     *
     * @return array
     */
    public function getTableNames(): array
    {
        return $this->tableNames;
    }

    /**
     * Return all the fields from the sub nodes/commands.
     *
     * @return array
     */
    public function getFields(): array
    {
        $fields = [];

        foreach ($this->getNodes() as $node) {
            if ($node instanceof AbstractNode) {
                $fields = \array_merge($fields, $node->getFields());
            } else if ($node instanceof Command) {
                $fields[] = $node->getField();
            }
        }

        return \array_unique($fields);
    }

    /**
     * Return all the fields and constraints from the sub nodes/commands.
     *
     * @return array
     */
    public function getFieldsAndConstraints(): array
    {
        $fields = [];

        foreach ($this->getNodes() as $node) {
            if ($node instanceof AbstractCommand) {
                $fields[] = $node->getField();
            } else {
                $fields = \array_merge($fields, $node->getFieldsAndConstraints());
            }
        }

        return \array_unique($fields);
    }

    /**
     * Remove a sub node/command.
     *
     * @param  integer $index
     * @return void
     */
    protected function removeNode(int $index)
    {
        unset($this->nodes[$index]);

        $this->nodes = \array_values($this->nodes);
    }

    /**
     * Remove sub nodes/commands.
     *
     * @param  integer $firstIndex
     * @param  integer $lastIndex
     * @return void
     */
    protected function removeNodes(int $firstIndex, int $lastIndex)
    {
        \array_splice($this->nodes, $firstIndex, ($lastIndex - $firstIndex));

        $this->nodes = \array_values($this->nodes);
    }

    /**
     * Insert a new sub node/command.
     *
     * @param  AbstractNode|AbstractCommand $node
     * @param  integer                      $index
     * @return void
     */
    protected function insertNode($node, int $index)
    {
        $this->insertNodes([$node], $index);
    }

    /**
     * Add new sub nodes/commands.
     *
     * @param  array   $nodes
     * @param  integer $index
     * @return void
     */
    protected function insertNodes(array $nodes, int $index)
    {
        $this->nodes = \array_values(\array_merge(
            \array_slice($this->nodes, 0, $index),
            $nodes,
            \array_slice($this->nodes, $index),
        ));
    }

    /**
     * Move sub nodes/commands to a new index.
     *
     * @param  integer $firstIndex
     * @param  integer $lastIndex
     * @param  integer $newIndex
     * @return void
     */
    protected function moveNodes(int $firstIndex, int $lastIndex, int $newIndex)
    {
        for ($i = $firstIndex; $i <= $lastIndex; $i++ && $newIndex++) {
            $element = \array_splice($this->nodes, $i, 1);

            \array_splice($this->nodes, $newIndex, 0, $element);
        }

        $this->nodes = \array_values($this->nodes);
    }

    /**
     * Move a specific sub nodes/commands to a new index.
     *
     * @param  integer $currentIndex
     * @param  integer $newIndex
     * @return void
     */
    protected function moveNode(int $currentIndex, int $newIndex)
    {
        $this->moveNodes($currentIndex, $currentIndex, $newIndex);
    }

    /**
     * Unpack all sub nodes to group only sub commands.
     *
     * @return self
     */
    public function unpack()
    {
        for ($i = 0; $i < \count($this->getNodes()); $i++) {
            $node = $this->getNodes()[$i];

            if ($node instanceof AbstractNode) {
                $subNodes = $node->organize()->optimize()->getNodes();

                $this->removeNode($i);
                $this->insertNodes($subNodes, $i--);
            }
        }

        return $this;
    }

    /**
     * Pack together into meta nodes, all commands sharing the same table.
     *
     * @return self
     */
    public function pack()
    {
        $firstIndex = null;
        $commonTable = null;

        // If we are packing into meta nodes, we need to know if they are already created or not.
        $passedTables = \array_map(function ($node) {
            return $node->getTableName();
        }, Migrations::getActualNode()->getNodes());

        for ($i = 0; $i < \count($this->getNodes()); $i++) {
            $node = $this->getNodes()[$i];

            if ($node instanceof AbstractNode) {
                $subNodes = $node->organize()->optimize()->getNodes();

                $this->removeNode($i);
                $this->insertNodes($subNodes, $i--);
            } else {
                if (\is_null($firstIndex)) {
                    $firstIndex = $i;
                    $commonTable = $node->getTableName();
                } else if ($node->getTableName() !== $commonTable) {
                    $lastIndex = $i;
                    $subNodes = \array_slice($this->nodes, $firstIndex, ($lastIndex - $firstIndex));

                    if (!Metas::hasForTableName($commonTable)) {
                        $metaType = 'delete';
                    } else if (in_array($commonTable, $passedTables)) {
                        $metaType = 'update';
                    } else {
                        $metaType = 'create';
                        $passedTables[] = $commonTable;
                    }

                    $packNode = new MetaNode($subNodes, $commonTable, $metaType);

                    // Do not handle the just created node.
                    $i -= (\count($subNodes) - 1);

                    $this->removeNodes($firstIndex, $lastIndex);
                    $this->insertNode($packNode->organize()->optimize(), $firstIndex);

                    $firstIndex = $i;
                    $commonTable = $node->getTableName();
                }
            }
        }

        if (!\is_null($firstIndex)) {
            $subNodes = \array_slice($this->nodes, $firstIndex, (\count($this->getNodes()) - $firstIndex));

            if (!Metas::hasForTableName($commonTable)) {
                $metaType = 'delete';
            } else if (\in_array($commonTable, $passedTables)) {
                $metaType = 'update';
            } else {
                $metaType = 'create';
            }

            $packNode = new MetaNode($subNodes, $commonTable, $metaType);

            $this->removeNodes($firstIndex, \count($this->getNodes()));
            $this->insertNode($packNode->organize()->optimize(), $firstIndex);
        }

        return $this;
    }

    /**
     * This method is called when the node is asked to be organized.
     *
     * @return void
     */
    abstract protected function organizing();

    /**
     * Organize all the sub nodes/commands.
     * It is usefull in order to respect constraints.
     * It only can be organized one time.
     *
     * @return self
     */
    public function organize()
    {
        if ($this->organized) {
            return $this;
        }

        $this->organizing();

        $this->organized = true;

        return $this;
    }

    /**
     * This method is called when the node is asked to be optimized.
     *
     * @return void
     */
    abstract protected function optimizing();

    /**
     * Optimize all the sub nodes/commands.
     * It is usefull in order to avoid multiple meta nodes / files generation.
     * It only can be optimized one time.
     *
     * @return self
     */
    public function optimize()
    {
        if (!$this->organized) {
            throw new \LogicException('This migration needs to be organized first !');
        }

        if ($this->optimized) {
            return $this;
        }

        $this->optimizing();

        $this->optimized = true;

        return $this;
    }
}
