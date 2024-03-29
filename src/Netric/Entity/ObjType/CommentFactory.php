<?php

/**
 * Comment entity type
 *
 * @author Sky Stebnicki <sky.stebnicki@aereus.com>
 * @copyright 2015 Aereus
 */

namespace Netric\Entity\ObjType;

use Netric\ServiceManager\ServiceLocatorInterface;
use Netric\Entity\EntityFactoryInterface;
use Netric\EntityDefinition\EntityDefinition;
use Netric\Entity\EntityLoaderFactory;
use Netric\EntityGroupings\GroupingLoaderFactory;

/**
 * Create a new comment entity
 */
class CommentFactory implements EntityFactoryInterface
{
    /**
     * Entity creation factory
     *
     * @param ServiceLocatorInterface $serviceLocator ServiceLocator for injecting dependencies
     * @param EntityDefinition $def The definition of this type of object
     * @return EntityInterface CommentEntity
     */
    public static function create(ServiceLocatorInterface $serviceLocator, EntityDefinition $def)
    {
        $entityLoader = $serviceLocator->get(EntityLoaderFactory::class);
        $groupingLoader = $serviceLocator->get(GroupingLoaderFactory::class);
        return new CommentEntity($def, $entityLoader, $groupingLoader);
    }
}
