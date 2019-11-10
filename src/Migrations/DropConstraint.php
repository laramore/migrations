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
    public function __construct(string $tableName, string $key, Constraint $reversedConstraint=null)
    {
        parent::__construct($tableName, $key, \is_null($reversedConstraint) ? [] : $reversedConstraint->getNeeds(), []);

        if (!\is_null($reversedConstraint)) {
            $this->reverse = $reversedConstraint->getCommand();
        }
    }

    /**
     * Create a default index name for the table.
     *
     * @return string
     */
    public function getIndexName(): string
    {
        return $this->getAttname();
    }
}
