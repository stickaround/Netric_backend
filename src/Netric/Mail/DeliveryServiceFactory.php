<?php
/**
 * @author Sky Stebnicki <sky.stebnicki@aereus.com>
 * @copyright 2016 Aereus
 */
namespace Netric\Mail;

use Netric\ServiceManager;
use Netric\EntityGroupings\LoaderFactory;
use Netric\Entity\EntityLoaderFactory;
use Netric\EntityQuery\Index\IndexFactory;
use Netric\FileSystem\FileSystemFactory;

/**
 * Create a service for delivering mail
 */
class DeliveryServiceFactory implements ServiceManager\AccountServiceFactoryInterface
{
    /**
     * Service creation factory
     *
     * @param ServiceManager\AccountServiceManagerInterface $sl ServiceLocator for injecting dependencies
     * @return DeliveryService
     */
    public function createService(ServiceManager\AccountServiceManagerInterface $sl)
    {
        $user = $sl->getAccount()->getUser();
        $entityLoader = $sl->get(EntityLoaderFactory::class);
        $groupingsLoader = $sl->get(LoaderFactory::class);
        $log = $sl->get("Log");
        $index = $sl->get(IndexFactory::class);
        $fileSystem = $sl->get(FileSystemFactory::class);

        return new DeliveryService(
            $log,
            $entityLoader,
            $groupingsLoader,
            $index,
            $fileSystem
        );
    }
}
