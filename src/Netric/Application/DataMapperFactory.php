<?php
namespace Netric\Application;

use Netric\ServiceManager\ApplicationServiceFactoryInterface;
use Netric\ServiceManager\ServiceLocatorInterface;

use Netric\Config\Config;

/**
 * Create a new Application DataMapper service
 */
class DataMapperFactory implements ApplicationServiceFactoryInterface
{
    /**
     * Service creation factory
     *
     * @param ServiceLocatorInterface $serviceLocator ServiceLocator for injecting dependencies
     * @return DataMapperInterface
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = $serviceLocator->get(Config::class);
        return new DataMapperPgsql($config->db->host, $config->db->sysdb, $config->db->user, $config->db->password);
    }
}
