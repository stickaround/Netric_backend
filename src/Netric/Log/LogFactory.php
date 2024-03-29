<?php
/**
 * Service factory for Log
 *
 * @author Marl Tumulak <marl.tumulak@aereus.com>
 * @copyright 2016 Aereus
 */
namespace Netric\Log;

use Netric\ServiceManager\ApplicationServiceFactoryInterface;
use Netric\ServiceManager\ServiceLocatorInterface;

/**
 * Create a Log service
 */
class LogFactory implements ApplicationServiceFactoryInterface
{
    /**
     * Service creation factory
     *
     * @param ServiceLocatorInterface $serviceLocator For loading dependencies
     * @return LogInterface
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return $serviceLocator->getApplication()->getLog();
    }
}
