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
     * Type of the constraint.
     *
     * @var string
     */
    protected $constraint = 'dropForeign';

    /**
     * Create a new drop command for a specific key.
     *
     * @param string     $tableName
     * @param string     $key
     * @param Constraint $reversedConstraint
     */
    public function __construct(string $tableName, string $key, Constraint $reversedConstraint)
    {
        parent::__construct($tableName, $key, $reversedConstraint->getNeeds(), []);

        $this->reverse = $reversedConstraint->getCommand();
    }
}
