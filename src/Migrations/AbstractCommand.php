<?php
/**
 * Abstract command for Command and Constraint.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Migrations;

abstract class AbstractCommand
{
    /**
     * Defined reversed command.
     *
     * @var AbstractCommand
     */
    protected $reverse;

    /**
     * Define the reversed command.
     *
     * @param AbstractCommand $reverse
     * @return void
     */
    public function setReverse(AbstractCommand $reverse)
    {
        $this->reverse = $reverse;
    }

    /**
     * Generate a new reversed command.
     *
     * @return AbstractCommand
     */
    abstract protected function generateReverse(): AbstractCommand;

    /**
     * Return the reversed command.
     *
     * @return AbstractCommand
     */
    public function getReverse(): AbstractCommand
    {
        if ($this->reverse) {
            return $this->reverse;
        }

        return $this->generateReverse();
    }

    /**
     * Return the properties for migration generation.
     *
     * @return array
     */
    public function getMigrationProperties(): array
    {
        return [];
    }
}
