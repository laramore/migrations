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

    protected function getMetaContraintTables(MetaNode $node)
    {
        return array_unique(array_merge([], ...array_map(function (Contraint $contraint) {
            return array_map(function (array $need) {
                return $need['table'];
            }, $contraint->getNeeds());
        }, $node->getContraintNodes())));
    }

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

    protected function orderBeforeOrganizing()
    {
        $this->pack();

        $nbrOfNodes = count($this->getNodes());

        for ($i = 0; $i < $nbrOfNodes; $i++) {
            $node1 = $this->getNodes()[$i];
            $tableNames1 = $this->getMetaContraintTables($node1);

            if (count($tableNames1)) {
                for ($j = ($i + 1); $j < $nbrOfNodes; $j++) {
                    $node2 = $this->getNodes()[$j];
                    $tableNames2 = $this->getMetaContraintTables($node2);

                    if (count($tableNames1) < count($tableNames2) || (count($tableNames1) === count($tableNames2) && in_array($node2->getTableName(), $tableNames1))) {
                        $this->swapNodes($i, $j);
                        break;
                    }
                }
            } else {
                $this->moveNode($i, 0);
            }
        }
    }

    protected function organizing()
    {
        $this->orderBeforeOrganizing();

        $this->unpack();

        $fields = [];
        $nbrOfNodes = count($this->getNodes());

        for ($i = 0; $i < $nbrOfNodes; $i++) {
            $nodeToMove = $this->getNodes()[$i];

            if ($nodeToMove instanceof Contraint) {
                $neededFields = $nodeToMove->getFields();
                $missingFields = $this->arrayRecursiveDiff($neededFields, $fields);

                if ($missingFieldsCount = count($missingFields)) {
                    for ($j = ($i + 1); $j < $nbrOfNodes; $j++) {
                        $nodeToCheck = $this->getNodes()[$j];

                        if ($nodeToCheck instanceof Command) {
                            $missingFields = $this->arrayRecursiveDiff($missingFields, [$nodeToCheck->getField()]);
                        } else if ($nodeToCheck instanceof AbstractNode) {
                            $missingFields = $this->arrayRecursiveDiff($missingFields, $nodeToCheck->getFields());
                        }

                        if (count($missingFields) === 0) {
                            if ($nodeToCheck instanceof Command) {
                                $groupedTables = [$nodeToCheck->getTableName()];
                            } else if ($nodeToCheck instanceof AbstractNode) {
                                $groupedTables = $nodeToCheck->getTableNames();
                            }

                            for ($k = $j; $k < $nbrOfNodes; $k++) {
                                $nodeNotToIsolate = $this->getNodes()[$k];

                                if ($nodeNotToIsolate instanceof Command) {
                                    $tables = [$nodeNotToIsolate->getTableName()];
                                } else if ($nodeNotToIsolate instanceof AbstractNode) {
                                    $tables = $nodeNotToIsolate->getTableNames();
                                }

                                if (!count(array_intersect($groupedTables, $tables))) {
                                    break;
                                }

                                $j = $k;
                            }

                            break;
                        }
                    }

                    if ($missingFieldsCount !== count($missingFields)) {
                        $this->moveNode($i--, $j);
                    }
                }
            } else {
                if ($nodeToMove instanceof Command) {
                    $fields[] = $nodeToMove->getField();
                } else if ($nodeToMove instanceof AbstractNode) {
                    $fields = array_merge($fields, $nodeToMove->getFields());
                }
            }
        }
    }

    protected function optimizing()
    {
        $this->unpack();

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
                                        $this->moveNode($j, ($k - 1));
                                        $moved = true;
                                        $j--;
                                        break;
                                    }
                                }
                            }

                            if (!$moved && $nodeToMove === $this->getNodes()[$j]) {
                                // Move after the contraint as it is not applied to this node.
                                $this->moveNode($j, $i);
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
                            $this->moveNode($j, $i);
                            $movedNodes[] = $nodeToMove;
                            $j++;
                        }
                    } else {
                        if ($this->contraintCanMove($nodeToMove, $i, $j)) {
                            $this->moveNode($j, $i);
                            $movedNodes[] = $nodeToMove;
                            $j++;
                        }
                    }
                }
            }
        }

        for ($i = ($nbrOfNodes - 1); $i > 0; $i--) {
            $node = $this->getNodes()[$i];
            $movedNodes = [];

            if ($node instanceof Contraint) {
                $movingTable = $node->getTableName();

                for ($j = 0; $j <= $i; $j++) {
                    $nodeToMove = $this->getNodes()[$j];

                    if (in_array($nodeToMove, $movedNodes)) {
                        continue;
                    }

                    if ($nodeToMove instanceof Contraint) {
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

    protected function arrayRecursiveDiff($aArray1, $aArray2)
    {
        $aReturn = [];

        foreach ($aArray1 as $mKey => $mValue) {
            if (array_key_exists($mKey, $aArray2)) {
                if (is_array($mValue)) {
                    $aRecursiveDiff = $this->arrayRecursiveDiff($mValue, $aArray2[$mKey]);
                    if (count($aRecursiveDiff)) {
                        $aReturn[$mKey] = $aRecursiveDiff;
                    }
                } else {
                    if ($mValue != $aArray2[$mKey]) {
                        $aReturn[$mKey] = $mValue;
                    }
                }
            } else {
                $aReturn[$mKey] = $mValue;
            }
        }

        return $aReturn;
    }

    protected function getResultedCommand(array &$nodes, Command $command)
    {
        foreach ($nodes as $key => $node) {
            if ($node instanceof Command && $node->getField() === $command->getField()) {
                $nodeProperties = $node->getProperties();
                $commandProperties = $command->getProperties();
                $commandType = $command->getType();

                $oldProperties = $this->arrayRecursiveDiff($nodeProperties, $commandProperties);
                $newProperties = $this->arrayRecursiveDiff($commandProperties, $nodeProperties);

                unset($nodes[$key]);

                if ($count = (count($newProperties) + count($oldProperties))) {
                    if ($count === 2 && count(array_diff([$node->getType(), $commandType], ['datetime', 'timestamp'])) === 0) {
                        return null;
                    }

                    return new ChangeCommand($command->getTableName(), $commandType, $command->getAttname(), $newProperties, $oldProperties);
                } else {
                    return null;
                }
            }
        }

        return $command;
    }

    protected function getResultedContraint(array &$nodes, Contraint $contraint)
    {
        $command = $contraint->getCommand();

        foreach ($nodes as $key => $node) {
            if ($node instanceof Contraint) {
                $nodeCommand = $node->getCommand();

                if ($node->getField() === $contraint->getField()) {
                    $oldProperties = $this->arrayRecursiveDiff($nodeCommand->getProperties(), $command->getProperties());
                    $newProperties = $this->arrayRecursiveDiff($command->getProperties(), $nodeCommand->getProperties());

                    unset($nodes[$key]);

                    if ((count($newProperties) + count($oldProperties))) {
                        $dropNode = $node->getReverse();
                        $reversedCommand = $dropNode->getReverse();
                        $dropNode->setReverse($contraint->getReverse());
                        $contraint->setReverse($reversedCommand);

                        return [
                            $dropNode,
                            $contraint,
                        ];
                    } else {
                        return null;
                    }
                }
            }
        }

        return $contraint;
    }

    public function diff(Node $node): Node
    {
        $nodes = (new static($this->getNodes()))->unpack()->getNodes();
        $substractNodes = (new static($node->getNodes()))->unpack()->getNodes();
        $diffNodes = [];

        foreach ($nodes as $nodeToCheck) {
            if ($nodeToCheck instanceof Command) {
                $resultedNode = $this->getResultedCommand($substractNodes, $nodeToCheck);
            } else {
                $resultedNode = $this->getResultedContraint($substractNodes, $nodeToCheck);
            }

            if ($resultedNode) {
                if (is_array($resultedNode)) {
                    $diffNodes = array_merge($diffNodes, $resultedNode);
                } else {
                    $diffNodes[] = $resultedNode;
                }
            }
        }

        return (new static($diffNodes));
    }
}
