<?php
/**
 * Service factory for the Entity Definition DataMapper
 *
 * @author Marl Tumulak <marl.tumulak@aereus.com>
 * @copyright 2016 Aereus
 */
namespace Netric\EntityDefinition\DataMapper;

use Netric\ServiceManager;

/**
 * Create a Entity Definition DataMapper service
 */
class DataMapperFactory implements ServiceManager\AccountServiceFactoryInterface
{
    /**
     * Service creation factory
     *
     * @param ServiceManager\AccountServiceManagerInterface $sl ServiceLocator for injecting dependencies
     * @return DbInterface
     */
    public function createService(ServiceManager\AccountServiceManagerInterface $sl)
    {
        return new EntityDefinitionRdbDataMapper($sl->getAccount());
    }
}