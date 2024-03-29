<?php

/**
 * @author Sky Stebnicki <sky.stebnicki@aereus.com>
 * @copyright 2016 Aereus
 */

namespace Netric\WorkerMan;

use Netric\ServiceManager\ApplicationServiceFactoryInterface;
use Netric\ServiceManager\ServiceLocatorInterface;
use Netric\Config\ConfigFactory;
use Netric\Log\LogFactory;
use Netric\WorkerMan\Queue\InMemory;
use Netric\WorkerMan\Queue\JobQueue;
use RuntimeException;

/**
 * Handle setting up a worker service
 */
class WorkerServiceFactory implements ApplicationServiceFactoryInterface
{
    /**
     * Service creation factory
     *
     * @param ServiceLocatorInterface $serviceLocator ServiceLocator for injecting dependencies
     * @return WorkerService
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = $serviceLocator->get(ConfigFactory::class);
        $log = $serviceLocator->get(LogFactory::class);
        $workerFactory = new WorkerFactory($serviceLocator);

        $queue = null;

        switch ($config->workers->queue) {
            case 'memory':
                $queue = new InMemory($workerFactory);
                break;
            case 'jobqueue':
                $queue = new JobQueue($config->workers->server);
                break;
            default:
                throw new RuntimeException("Worker queue not supported: " . $config->workers->queue);
                break;
        }

        return new WorkerService($queue, $workerFactory, $config->workers->server);
    }
}
