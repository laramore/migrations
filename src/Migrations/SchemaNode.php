<?php
/**
 * Correspond to a migration node from the Laravel Schema.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Migrations;

use Laramore\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;

class SchemaNode extends Node
{
    /**
     * Generate schema nodes with Laravel migrations.
     */
    public function __construct()
    {
        $this->setNodes(Builder::getBlueprints());
    }

    /**
     * Define the sub nodes/commands.
     *
     * @param array $nodes
     * @return void
     */
    protected function setNodes(array $nodes)
    {
        $this->nodes = array_map(function (Blueprint $node) {
            return new BlueprintNode($node);
        }, $nodes);
    }

    /**
     * This method is called when the node is asked to be organized.
     *
     * @return void
     */
    protected function organizing()
    {
        $this->unpack();

        // Sum up creations, updates and deletions.
        for ($i = 0; $i < \count($this->nodes); $i++) {
            $node = $this->nodes[$i];

            if ($node instanceof DropCommand) {
                $field = $node->getField();

                for ($j = 0; $j < $i; $j++) {
                    $nodeToCheck = $this->nodes[$j];

                    if ($nodeToCheck instanceof Command && $nodeToCheck->getField() === $field) {
                        $this->removeNode($i);
                        $this->removeNode($j--);
                        $i -= 2;

                        for ($k = $i; $k > 0; $k--) {
                            if ($nodeToCheck instanceof ChangeCommand && $nodeToCheck->getField() === $field) {
                                $this->removeNode($k);
                                $i--;
                            }
                        }
                    }
                }
            }

            if ($node instanceof DropConstraint) {
                $index = $node->getIndexName();

                for ($j = 0; $j < $i; $j++) {
                    $nodeToCheck = $this->nodes[$j];

                    if ($nodeToCheck instanceof Constraint && $nodeToCheck->getIndexName() === $index) {
                        $this->removeNode($i);
                        $this->removeNode($j--);
                        $i -= 2;
                    }
                }
            }

            if ($node instanceof DropIndex) {
                $index = $node->getIndexName();

                for ($j = 0; $j < $i; $j++) {
                    $nodeToCheck = $this->nodes[$j];

                    if ($nodeToCheck instanceof Index && $nodeToCheck->getIndexName() === $index) {
                        $this->removeNode($i);
                        $this->removeNode($j--);
                        $i -= 2;
                    } else if ($nodeToCheck instanceof Command) {
                        $index = \str_replace(
                            ['-', '.'], '_',
                            \strtolower($nodeToCheck->getTableName().'_'.$nodeToCheck->getAttname().'_unique')
                        );
                        $this->removeNode($i);
                        $this->removeNode($j--);
                        $i -= 2;
                    }
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
        $this->pack();
    }
}
