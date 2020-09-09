<?php

declare(strict_types=1);

namespace Netric\EntitySync;

use Netric\EntitySync\Collection\CollectionFactoryFactory;
use Netric\Db\Relational\RelationalDbContainerFactory;
use Netric\ServiceManager\AccountServiceFactoryInterface;
use Netric\ServiceManager\AccountServiceManagerInterface;
use Netric\WorkerMan\WorkerServiceFactory;
use Netric\ServiceManager;

/**
 * Create a Entity Sync Commit DataMapper service
 */
class DataMapperFactory implements ServiceManager\AccountServiceFactoryInterface
{
    /**
     * Service creation factory
     *
     * @param AccountServiceManagerInterface $serviceLocator ServiceLocator for injecting dependencies
     * @return DataMapperInterface
     */
    public function createService(AccountServiceManagerInterface $serviceLocator)
    {
        $relationalDbCon = $serviceLocator->get(RelationalDbContainerFactory::class);
        $collectionFactory = $serviceLocator->get(CollectionFactoryFactory::class);
        $workerService = $serviceLocator->get(WorkerServiceFactory::class);

        return new DataMapperRdb($relationalDbCon, $workerService, $collectionFactory);
    }
}
