<?php
/**
 * Abstract command for Command and Contraint.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

namespace Laramore\Migrations;

abstract class AbstractCommand
{
    protected $reverse;

    public function setReverse(AbstractCommand $reverse)
    {
        $this->reverse = $reverse;
    }

    abstract protected function generateReverse(): AbstractCommand;

    public function getReverse()
    {
        if ($this->reverse) {
            return $this->reverse;
        }

        return $this->generateReverse();
    }
}
