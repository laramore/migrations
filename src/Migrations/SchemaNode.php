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
