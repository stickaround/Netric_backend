<?php

/**
 * Factory to create a new TestAction
 *
 * @author Sky Stebnicki <sky.stebnicki@aereus.com>
 * @copyright 2015 Aereus
 */

namespace Netric\WorkFlowLegacy\Action;

use Netric\ServiceManager\AccountServiceManagerInterface;
use Netric\Entity\EntityLoaderFactory;

/**
 * Create a new TestAction
 */
class TestActionFactory
{
    /**
     * Create a new action based on a name
     *
     * @param AccountServiceManagerInterface $serviceLocator For loading dependencies
     * @return ActionInterface
     */
    public static function create(AccountServiceManagerInterface $serviceLocator)
    {
        // Return a new TestAction
        $entityLoader = $serviceLocator->get(EntityLoaderFactory::class);
        $actionFactory = new ActionFactory($serviceLocator);
        return new TestAction($entityLoader, $actionFactory);
    }
}