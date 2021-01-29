<?php

namespace Netric\Controller;

use Netric\Mvc\ControllerFactoryInterface;
use Netric\Mvc\ControllerInterface;
use Netric\ServiceManager\ServiceLocatorInterface;
use Netric\Account\AccountContainerFactory;
use Netric\Authentication\AuthenticationServiceFactory;
use Netric\Account\AccountSetupFactory;
use Netric\Application\DatabaseSetupFactory;
use Netric\Log\LogFactory;

/**
 * Construct the ModuleControllerFactory for interacting with email messages
 */
class ModuleControllerFactory implements ControllerFactoryInterface
{
    /**
     * Construct a controller and return it
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return ControllerInterface
     */
    public function get(ServiceLocatorInterface $serviceLocator): ControllerInterface
    {
        $accountContainer = $serviceLocator->get(AccountContainerFactory::class);
        $authService = $serviceLocator->get(AuthenticationServiceFactory::class);        
        $accountSetup = $serviceLocator->get(AccountSetupFactory::class);
        $dbSetup = $serviceLocator->get(DatabaseSetupFactory::class);
        $log = $serviceLocator->get(LogFactory::class);
        $application = $serviceLocator->getApplication();

        return new ModuleController(
            $accountContainer,
            $authService,
            $accountSetup,
            $dbSetup,
            $log,
            $application
        );
    }
}
