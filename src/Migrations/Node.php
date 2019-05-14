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
            for ($j = ($i + 1); $j < $nbrOfNodes; $j++) {
                $node1 = $this->getNodes()[$i];
                $node2 = $this->getNodes()[$j];
                $tableNames1 = $this->getMetaContraintTables($node1);
                $tableNames2 = $this->getMetaContraintTables($node2);

                if (count($tableNames1) < count($tableNames2) || (count($tableNames1) === count($tableNames2) && in_array($node2->getTableName(), $tableNames1))) {
                    $this->swapNodes($i, $j);
                }
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
                $missingFields = array_diff($neededFields, $fields);

                if ($missingFieldsCount = count($missingFields)) {
                    for ($j = ($i + 1); $j < $nbrOfNodes; $j++) {
                        $nodeToCheck = $this->getNodes()[$j];

                        if ($nodeToCheck instanceof Command) {
                            $missingFields = array_diff($missingFields, [$nodeToCheck->getField()]);
                        } else if ($nodeToCheck instanceof AbstractNode) {
                            $missingFields = array_diff($missingFields, $nodeToCheck->getFields());
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

    protected function getResultedCommand(array $nodes, Command $command)
    {
        foreach ($nodes as $node) {
            if ($node instanceof Command && $node->getField() === $command->getField()) {
                if ((count(array_diff($node->getProperties(), $command->getProperties())) + count(array_diff($command->getProperties(), $node->getProperties())))) {
                    throw new \Exception('Calculate the diff.');
                } else {
                    return null;
                }
            }
        }

        return $command;
    }

    protected function getResultedContraint(array $nodes, Contraint $contraint)
    {
        $command = $contraint->getCommand();

        foreach ($nodes as $node) {
            if ($node instanceof Contraint) {
                $nodeCommand = $node->getCommand();

                if ($nodeCommand->getField() === $command->getField()) {
                    if ((count(array_diff($nodeCommand->getProperties(), $command->getProperties())) + count(array_diff($command->getProperties(), $nodeCommand->getProperties())))) {
                        throw new \Exception('Calculate the diff.');
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

                if ($resultedNode) {
                    $diffNodes[] = $resultedNode;
                }
            } else {
                $resultedNode = $this->getResultedContraint($substractNodes, $nodeToCheck);
            }
        }

        return (new static($diffNodes))->organize()->optimize();
    }
}
