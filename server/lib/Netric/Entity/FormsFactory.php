<?php
/**
 * Service factory for the Forms
 *
 * @author Sky Stebnicki <sky.stebnicki@aereus.com>
 * @copyright 2015 Aereus
 */
namespace Netric\Entity;

use Netric\ServiceManager;

/**
 * Create a new Forms service for getting and saving forms
 *
 * @package Netric\FileSystem
 */
class FormsFactory implements ServiceManager\AccountServiceLocatorInterface
{
    /**
     * Service creation factory
     *
     * @param \Netric\ServiceManager\AccountServiceManagerInterface $sl ServiceLocator for injecting dependencies
     * @return FileSystem
     */
    public function createService(ServiceManager\AccountServiceManagerInterface $sl)
    {
        $dbh = $sl->get("Db");
        $config = $sl->get("Config");
        return new Forms($dbh, $config);
    }
}
