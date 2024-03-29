<?php

namespace Netric\Workflow\ActionExecutor;

use Netric\Config\ConfigFactory;
use Netric\ServiceManager\ServiceLocatorInterface;
use Netric\Entity\EntityLoaderFactory;
use Netric\Entity\ObjType\WorkflowActionEntity;
use Netric\Curl\HttpCallerFactory;


/**
 * Factory to create a new StartWorkflowAction
 */
class WebhookActionExecutorFactory
{
    /**
     * Construct action executor with dependencies
     *
     * @param ServiceLocatorInterface $serviceLocator For loading dependencies
     * @return ActionExectorInterface
     */
    public static function create(
        ServiceLocatorInterface $serviceLocator,
        WorkflowActionEntity $actionEntity
    ): ActionExecutorInterface {
        // Setup dependencies
        $entityLoader = $serviceLocator->get(EntityLoaderFactory::class);
        $config = $serviceLocator->get(ConfigFactory::class);
        $httpCaller = $serviceLocator->get(HttpCallerFactory::class);

        return new WebhookActionExecutor(
            $entityLoader,
            $actionEntity,
            $config->application_url,
            $httpCaller
        );
    }
}
