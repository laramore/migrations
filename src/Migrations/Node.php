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

class Node extends AbstractNode
{
    /**
     * Indicate if a constraint can move from an index to to another.
     *
     * @param  Constraint $node
     * @param  integer    $firstIndex
     * @param  integer    $lastIndex
     * @return boolean
     */
    protected function constraintCanMove(Constraint $node, int $firstIndex, int $lastIndex): bool
    {
        for ($i = $firstIndex; $i < $lastIndex; $i++) {
            $nodeToCheck = $this->getNodes()[$i];

            if ($nodeToCheck instanceof Command && \in_array($nodeToCheck->getField(), $node->getFields())) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return all required tables for a constraint.
     *
     * @param  Constraint $constraint
     * @return array
     */
    protected function getConstraintTables(Constraint $constraint): array
    {
        return \array_unique(\array_map(function (array $need) {
            return $need['table'];
        }, $constraint->getNeeds()));
    }

    /**
     * Return all required tables from many constraints.
     *
     * @param  MetaNode $node
     * @return array
     */
    protected function getMetaConstraintTables(MetaNode $node)
    {
        return \array_unique(\array_merge([], ...\array_map(function (Constraint $constraint) {
            return $this->getConstraintTables($constraint);
        }, $node->getConstraintNodes())));
    }

    /**
     * Swap two nodes from their index.
     *
     * @param  integer $firstIndex
     * @param  integer $secondIndex
     * @return void
     */
    protected function swapNodes(int $firstIndex, int $secondIndex)
    {
        if ($secondIndex < $firstIndex) {
            [$firstIndex, $secondIndex] = [$secondIndex, $firstIndex];
        }

        $this->moveNode($firstIndex, $secondIndex);

        if (($secondIndex - $firstIndex) > 1) {
            $this->moveNode(($secondIndex - 1), $firstIndex);
        }
    }

    /**
     * Pre order all nodes by grouping them by table.
     *
     * @return void
     */
    protected function orderBeforeOrganizing()
    {
        $this->pack();

        // Sometimes, commands and constraints are in separated meta nodes.
        // So, we regroup all of them in the first commune meta table.
        for ($i = 0; $i < \count($this->getNodes()); $i++) {
            $metaNode = $this->getNodes()[$i];
            $tableName = $metaNode->getTableName();

            for ($j = ($i + 1); $j < \count($this->getNodes()); $j++) {
                $metaNodeToCheck = $this->getNodes()[$j];

                // Here we got two meta nodes for a same meta.
                // We add all the nodes of the second in the first before deleting it.
                if ($tableName === $metaNodeToCheck->getTableName()) {
                    $metaNode->addNodes($metaNodeToCheck->getNodes());
                    $metaNode->organize()->optimize();

                    $this->removeNode($j--);
                }
            }
        }

        $nbrOfNodes = \count($this->getNodes());
        // Avoid swap looping between 2 nodes pointing one from the other.
        $jToAvoid = [];

        for ($i = 0; $i < $nbrOfNodes; $i++) {
            $jToAvoid[$i] = ($jToAvoid[$i] ?? null);

            $node1 = $this->getNodes()[$i];
            $tableNames1 = $this->getMetaConstraintTables($node1);

            // If the meta node has no constraints, just move it on the top, as it depends on nothing.
            if (\count($tableNames1)) {
                // For each meta nodes after ours, check if they have less constraints, if it is the case, swap them.
                for ($j = ($i + 1); $j < $nbrOfNodes; $j++) {
                    $node2 = $this->getNodes()[$j];
                    $tableNames2 = $this->getMetaConstraintTables($node2);

                    if ($jToAvoid[$i] === $j) {
                        $jToAvoid = null;
                        continue;
                    }

                    $cond = \count($tableNames1) > \count($tableNames2) ||
                        (\count($tableNames1) === \count($tableNames2) && \in_array($node2->getTableName(), $tableNames1));

                    if ($cond) {
                        // Avoid swap looping between 2 nodes pointing one from the other.
                        $jToAvoid[$i] = \in_array($node1->getTableName(), $tableNames2) ? $j : null;

                        $this->swapNodes($j, $i--);
                        break;
                    }
                }
            } else {
                $this->moveNode($i, 0);
            }
        }
    }

    /**
     * This method is called when the node is asked to be organized.
     *
     * @return void
     */
    protected function organizing()
    {
        $this->orderBeforeOrganizing();

        $this->unpack();

        $fields = [];
        $nbrOfNodes = \count($this->getNodes());

        // Here we move all constraints under the required fields.
        for ($i = 0; $i < $nbrOfNodes; $i++) {
            $nodeToMove = $this->getNodes()[$i];

            if ($nodeToMove instanceof Constraint) {
                $neededFields = $nodeToMove->getFields();
                $missingFields = \array_diff($neededFields, $fields);

                // Check if fields are required before this constraint.
                if ($missingFieldsCount = \count($missingFields)) {
                    for ($j = ($i + 1); $j < $nbrOfNodes; $j++) {
                        $nodeToCheck = $this->getNodes()[$j];

                        // If the node we need to check defines a new field, pop it (if it is required) in the missing list.
                        if ($nodeToCheck instanceof AbstractCommand) {
                            $missingFields = \array_diff($missingFields, [$nodeToCheck->getField()]);
                        } else if ($nodeToCheck instanceof AbstractNode) {
                            $missingFields = \array_diff($missingFields, $nodeToCheck->getFields());
                        }

                        // When we reached the last required field, we move the constraint just after.
                        if (\count($missingFields) === 0) {
                            if ($nodeToCheck instanceof AbstractCommand) {
                                $groupedTables = [$nodeToCheck->getTableName()];
                            } else if ($nodeToCheck instanceof AbstractNode) {
                                $groupedTables = $nodeToCheck->getTableNames();
                            }

                            // Move all related fields in the same time.
                            for ($k = ($j + 1); $k < $nbrOfNodes; $k++) {
                                $nodeNotToIsolate = $this->getNodes()[$k];

                                if ($nodeNotToIsolate instanceof AbstractCommand) {
                                    $tables = [$nodeNotToIsolate->getTableName()];
                                } else if ($nodeNotToIsolate instanceof AbstractNode) {
                                    $tables = $nodeNotToIsolate->getTableNames();
                                } else {
                                    break;
                                }

                                // If the node is not for the same tables, just stop searching under.
                                if (!\count(\array_intersect($groupedTables, $tables))) {
                                    break;
                                }

                                $j = $k;
                            }

                            break;
                        }
                    }

                    // If at least one required fields was under our constraint, move our constrait after the required fields.
                    if ($missingFieldsCount !== \count($missingFields)) {
                        $this->moveNode($i--, $j);
                    }
                }
            } else {
                // Add all fields as passed.
                if ($nodeToMove instanceof AbstractCommand) {
                    $fields[] = $nodeToMove->getField();
                } else if ($nodeToMove instanceof AbstractNode) {
                    $fields = array_merge($fields, $nodeToMove->getFields());
                }
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
        $this->unpack();

        $nbrOfNodes = \count($this->getNodes());

        // Move all nodes from the same table the lowest possible.
        // This is possible by grouping them to the latest constraint if existant.
        for ($i = ($nbrOfNodes - 1); $i > 0; $i--) {
            $node = $this->getNodes()[$i];
            $movedNodes = [];

            if ($node instanceof Constraint) {
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

                                if ($nodeToCheck instanceof Constraint) {
                                    if (in_array($nodeToMove->getField(), $nodeToCheck->getFields())) {
                                        $this->moveNode($j, ($k - 1));
                                        $moved = true;
                                        $j--;
                                        break;
                                    }
                                }
                            }

                            if (!$moved && $nodeToMove === $this->getNodes()[$j]) {
                                // Move after the constraint as it is not applied to this node.
                                $this->moveNode($j, $i);
                                $j--;
                            }

                            $movedNodes[] = $nodeToMove;
                        }
                    }
                }
            }
        }

        // Try to move on top all fields by group.
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
                            $this->moveNode($j, $i);
                            $movedNodes[] = $nodeToMove;
                            $j++;
                        }
                    } else {
                        if ($this->constraintCanMove($nodeToMove, $i, $j)) {
                            $this->moveNode($j, $i);
                            $movedNodes[] = $nodeToMove;
                            $j++;
                        }
                    }
                }
            }
        }

        // Try to pack together all constraints for the same table.
        for ($i = ($nbrOfNodes - 1); $i > 0; $i--) {
            $node = $this->getNodes()[$i];
            $movedNodes = [];

            if ($node instanceof Constraint) {
                $movingTable = $node->getTableName();

                for ($j = 0; $j <= $i; $j++) {
                    $nodeToMove = $this->getNodes()[$j];

                    if (in_array($nodeToMove, $movedNodes)) {
                        continue;
                    }

                    if ($nodeToMove instanceof Constraint) {
                        if ($nodeToMove->getTableName() === $movingTable) {
                            $this->moveNode($j, $i);
                            $movedNodes[] = $nodeToMove;
                        }
                    }
                }
            }
        }

        // Now, we can repack by subnodes all common commands with the same table.
        $this->pack();
    }

    /**
     * Make a diff on command properties.
     *
     * @param  array $properties1
     * @param  array $properties2
     * @return array
     */
    protected function propertiesDiff(array $properties1, array $properties2): array
    {
        $properties = [];

        foreach ($properties1 as $key1 => $value1) {
            if (\array_key_exists($key1, $properties2)) {
                if (\is_array($value1)) {
                    $diff = $this->propertiesDiff($value1, $properties2[$key1]);

                    if (\count($diff)) {
                        $properties[$key1] = $diff;
                    }
                } else {
                    if ($value1 != $properties2[$key1]) {
                        $properties[$key1] = $value1;
                    }
                }
            } else {
                $properties[$key1] = $value1;
            }
        }

        return $properties;
    }

    /**
     * Return the command or generate a change command depending on the actual nodes.
     *
     * @param  array   $nodes
     * @param  Command $command
     * @return Command|null
     */
    protected function getResultedCommand(array &$nodes, Command $command): ?Command
    {
        foreach ($nodes as $key => $node) {
            // If an identical command exists, check the differences.
            if ($node instanceof Command && $node->getField() === $command->getField()) {
                $nodeProperties = $node->getProperties();
                $commandProperties = $command->getProperties();
                $commandType = $command->getType();

                $oldProperties = $this->propertiesDiff($nodeProperties, $commandProperties);
                $newProperties = $this->propertiesDiff($commandProperties, $nodeProperties);

                unset($nodes[$key]);

                // If they are differences in properties, try to generate a change command.
                if ($count = (\count($newProperties) + \count($oldProperties))) {
                    // For some types, the difference needs to be calculated differently.
                    if ($count === 2 && !\count(\array_diff([$node->getType(), $commandType], ['datetime', 'timestamp']))) {
                        return null;
                    }

                    if (\in_array($commandType, ['enum', 'set'])) {
                        $oldProperties['allowed'] = $nodeProperties['allowed'];
                        $newProperties['allowed'] = $commandProperties['allowed'];
                    }

                    return new ChangeCommand($command->getTableName(), $commandType, $command->getAttname(),
                        $newProperties, $oldProperties);
                } else {
                    return null;
                }
            }
        }

        // Return the command if it needs to be created entierly.
        return $command;
    }

    /**
     * Return the constraint with or not a drop command depending on the actual nodes.
     *
     * @param  array      $nodes
     * @param  Constraint $constraint
     * @return Constraint|null
     */
    protected function getResultedConstraint(array &$nodes, Constraint $constraint): ?Constraint
    {
        $command = $constraint->getCommand();

        foreach ($nodes as $key => $node) {
            // If an identical constraints exists, check the differences.
            if ($node instanceof Constraint) {
                $nodeCommand = $node->getCommand();

                if ($node->getField() === $constraint->getField()) {
                    $oldProperties = $this->propertiesDiff($nodeCommand->getProperties(), $command->getProperties());
                    $newProperties = $this->propertiesDiff($command->getProperties(), $nodeCommand->getProperties());

                    unset($nodes[$key]);

                    // If they are changes, drop the last constraint and create the new one.
                    if ((\count($newProperties) + \count($oldProperties))) {
                        $dropNode = $node->getReverse();
                        $reversedCommand = $dropNode->getReverse();
                        $dropNode->setReverse($constraint->getReverse());
                        $constraint->setReverse($reversedCommand);

                        return [
                            $dropNode,
                            $constraint,
                        ];
                    } else {
                        return null;
                    }
                }
            }
        }

        // Return the constraint if it needs to be created entierly.
        return $constraint;
    }

    /**
     * Calculate and return a new node from the difference between this node and another.
     *
     * @param  Node $node
     * @return Node
     */
    public function diff(Node $node): Node
    {
        $nodes = (new static($this->getNodes()))->unpack()->getNodes();
        $substractNodes = (new static($node->getNodes()))->unpack()->getNodes();
        $diffNodes = [];

        // Compare for each sub nodes of this node.
        foreach ($nodes as $nodeToCheck) {
            if ($nodeToCheck instanceof Command) {
                $resultedNode = $this->getResultedCommand($substractNodes, $nodeToCheck);
            } else {
                $resultedNode = $this->getResultedConstraint($substractNodes, $nodeToCheck);
            }

            if ($resultedNode) {
                if (\is_array($resultedNode)) {
                    $diffNodes = \array_merge($diffNodes, $resultedNode);
                } else {
                    $diffNodes[] = $resultedNode;
                }
            }
        }

        // Add all diff nodes and all new sub nodes.
        return (new static(\array_merge([], $diffNodes, $substractNodes)));
    }
}
