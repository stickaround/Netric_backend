<?php
/**
 * @author Sky Stebnicki <sky.stebnicki@aereus.com>
 * @copyright 2016 Aereus
 */
namespace Netric\Account\Module\DataMapper;

use Netric\ServiceManager;

/**
 * Create a data mapper service for modules
 */
class DataMapperFactory implements ServiceManager\ServiceFactoryInterface
{
    /**
     * Service creation factory
     *
     * @param \Netric\ServiceManager\ServiceLocatorInterface $sl ServiceLocator for injecting dependencies
     * @return DataMapperDb
     */
    public function createService(ServiceManager\ServiceLocatorInterface $sl)
    {
        $dbh = $sl->get('Db');
        return new DataMapperDb($dbh);
    }
}
