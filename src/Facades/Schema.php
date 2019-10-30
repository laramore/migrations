<?php
/**
 * Proxy the Schema facade so we are able to get all migrations.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Facades;

use Illuminate\Support\Facades\Schema as BaseSchema;
use Laramore\Database\Schema\Builder;
use Migrations;

/**
 * @see \Illuminate\Support\Facades\Schema
 */
class Schema extends BaseSchema
{
    /**
     * Tell the facade if the migration manager is loading.
     *
     * @return boolean
     */
    public static function isProxied()
    {
        return Migrations::isLoadingMigrations();
    }

    /**
     * Get a schema builder instance for a connection.
     *
     * @param  string|null $name
     * @return \Illuminate\Database\Schema\Builder
     */
    public static function connection($name=null)
    {
        if (static::isProxied()) {
            return new Builder(static::$app['db']->connection($name));
        } else {
            return parent::getFacadeAccessor();
        }
    }

    /**
     * Get a schema builder instance for the default connection.
     *
     * @return \Illuminate\Database\Schema\Builder
     */
    protected static function getFacadeAccessor()
    {
        if (static::isProxied()) {
            return new Builder(static::$app['db']->connection());
        } else {
            return parent::getFacadeAccessor();
        }
    }
}
