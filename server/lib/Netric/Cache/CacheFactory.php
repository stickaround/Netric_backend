<?php
/**
 * Service factory for the Cache
 *
 * @author Marl Tumulak <marl.tumulak@aereus.com>
 * @copyright 2016 Aereus
 */
namespace Netric;

use Netric\ServiceManager;

/**
 * Create a Cache service
 *
 * @package Netric\Cache
 */
class CacheNewFactory implements ServiceManager\AccountServiceLocatorInterface
{
    /**
     * Service creation factory
     *
     * @param ServiceManager\AccountServiceManagerInterface $sl ServiceLocator for injecting dependencies
     * @return Cache
     */
    public function createService(ServiceManager\AccountServiceManagerInterface $sl)
    {
        return $sl->getAccount()->getApplication()->getCache();
    }
}
