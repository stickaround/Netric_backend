<?php

/**
 * File entity type
 *
 * @author Sky Stebnicki <sky.stebnicki@aereus.com>
 * @copyright 2015 Aereus
 */

namespace Netric\Entity\ObjType;

use Netric\ServiceManager\ServiceLocatorInterface;
use Netric\Entity\EntityFactoryInterface;
use Netric\EntityDefinition\EntityDefinition;

/**
 * Create a new folder entity
 */
class FileFactory implements EntityFactoryInterface
{
    /**
     * Entity creation factory
     *
     * @param ServiceLocatorInterface $serviceLocator ServiceLocator for injecting dependencies
     * @param EntityDefinition $def The definition of this type of object
     * @return EntityInterface FileEntity
     */
    public static function create(ServiceLocatorInterface $serviceLocator, EntityDefinition $def)
    {
        return new FileEntity($def);
    }
}
