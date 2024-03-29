<?php

/**
 * @author Sky Stebnicki <sky.stebnicki@aereus.com>
 * @copyright 2015 Aereus
 */

namespace Netric\Workflow\ActionExecutor\Exception;

/**
 * Indicate that an action is referening itself in a child action which is circular
 */
class CircularChildActionsException extends \InvalidArgumentException
{
}
