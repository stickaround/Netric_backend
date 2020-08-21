<?php

namespace Netric\Workflow\ActionExecutor;

use Netric\ServiceManager\AccountServiceManagerInterface;
use Netric\Entity\EntityLoaderFactory;

/**
 * Factory to create a new CreateEntityAction
 */
class CreateEntityActionFactory
{
    /**
     * Construct new action
     *
     * @param AccountServiceManagerInterface $serviceLocator For loading dependencies
     * @return ActionInterface
     */
    public static function create(AccountServiceManagerInterface $serviceLocator)
    {
        // Return a new TestAction
        $entityLoader = $serviceLocator->get(EntityLoaderFactory::class);
        $actionFactory = new ActionExecutorFactory($serviceLocator);
        return new CreateEntityActionExecutor($entityLoader, $actionFactory);
    }
}