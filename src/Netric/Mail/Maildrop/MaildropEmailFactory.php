<?php

declare(strict_types=1);

namespace Netric\Mail\Maildrop;

use Netric\Entity\EntityLoaderFactory;
use Netric\EntityGroupings\GroupingLoaderFactory;
use Netric\FileSystem\FileSystemFactory;
use Netric\ServiceManager\ApplicationServiceFactoryInterface;
use Netric\ServiceManager\ServiceLocatorInterface;

/**
 * Create instance of maildrop
 */
class MaildropEmailFactory implements ApplicationServiceFactoryInterface
{
    /**
     * Service creation factory
     *
     * @param ServiceLocatorInterface $serviceLocator ServiceLocator for injecting dependencies
     * @return DeliveryService
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return new MaildropEmail(
            $serviceLocator->get(EntityLoaderFactory::class),
            $serviceLocator->get(GroupingLoaderFactory::class),
            $serviceLocator->get(FileSystemFactory::class)
        );
    }
}
