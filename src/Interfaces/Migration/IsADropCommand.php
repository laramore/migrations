<?php
/**
 * Indicate that the command is a drop one.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2020
 * @license MIT
 */

namespace Laramore\Interfaces\Migration;

use Laramore\Migrations\AbstractCommand;

interface IsADropCommand
{
    /**
     * Define the reversed command.
     *
     * @param AbstractCommand $reverse
     * @return void
     */
    public function setReverse(AbstractCommand $reverse);

    /**
     * Return the reversed command.
     *
     * @return AbstractCommand
     */
    public function getReverse(): AbstractCommand;
}
