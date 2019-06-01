<?php
/**
 * Save all built migrations.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Database\Schema;

use Illuminate\Database\Schema\{
    Builder as BaseBuilder, Blueprint
};

/**
 * @see \Illuminate\Database\Schema\Builder
 */
class Builder extends BaseBuilder
{
    protected static $blueprints = [];

    /**
     * Execute the blueprint to build / modify the table.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @return void
     */
    protected function build(Blueprint $blueprint)
    {
        static::$blueprints[] = $blueprint;
    }

    public static function getBlueprints()
    {
        return static::$blueprints;
    }
}
