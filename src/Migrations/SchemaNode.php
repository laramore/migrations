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
use Laramore\Facades\{
    MetaManager, TypeManager
};

class SchemaNode extends Node
{
    public function __construct()
    {
        $this->setNodes(Builder::getBlueprints());
    }

    protected function setNodes(array $nodes)
    {
        $this->nodes = array_map(function (Blueprint $node) {
            return new BlueprintNode($node);
        }, $nodes);
    }

    protected function organizing()
    {
        // Cannot organize a DatabaseNode.
    }

    protected function optimizing()
    {
        // Cannot optimize a DatabaseNode.
    }
}
