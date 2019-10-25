<?php
/**
 * Correspond to a drop constraint.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Migrations;

class DropConstraint extends Constraint
{
    /**
     * The foreign key to drop.
     *
     * @var string
     */
    protected $key;

    /**
     * Type of the constraint.
     *
     * @var string
     */
    protected $constraint = 'dropForeign';

    /**
     * Create a new drop command for a specific key.
     *
     * @param string       $tableName
     * @param string|mixed $attname
     * @param string       $key
     * @param Constraint   $reversedConstraint
     */
    public function __construct(string $tableName, $attname, string $key, Constraint $reversedConstraint)
    {
        parent::__construct($tableName, $attname, $reversedConstraint->getNeeds(), []);

        $this->key = $key;
        $this->reverse = $reversedConstraint->getCommand();
    }

    /**
     * Return the command properties.
     *
     * @return array
     */
    public function getProperties(): array
    {
        return array_merge([
            $this->type => $this->key,
        ], $this->properties);
    }
}
