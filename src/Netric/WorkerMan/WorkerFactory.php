<?php

declare(strict_types=1);

namespace Netric\WorkerMan;

use Netric\ServiceManager\ServiceLocatorInterface;

/**
 * Factory for creating workers to be executed through the WorkerMan queue
 */
class WorkerFactory
{
    /**
     * ServiceLocator for injecting dependencies
     *
     * @var ServiceLocatorInterface
     */
    private $serviceManager = null;

    /**
     * Class constructor
     *
     * @param ServiceLocatorInterface $serviceLocator ServiceLocator for injecting dependencies
     */
    public function __construct(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceManager = $serviceLocator;
    }

    /**
     * Get a new instance of a worker based on the class name
     *
     * @param string $className
     * @return WorkerInterface|null
     */
    public function getWorkerByName(string $className): ?WorkerInterface
    {
        $fullWorkerName = $className;

        // Handle special CronMinitely jobs that are not namespaced
        // In the future we might ahve more generic jobs like CronHourly
        if (false === strpos($className, "\\")) {
            $fullWorkerName = "Netric\\WorkerMan\\Worker\\" . $className . "Worker";
        }

        $factoryClassName = $fullWorkerName . 'Factory';

        if (class_exists($factoryClassName)) {
            $workerFactory = new $factoryClassName();
            return $workerFactory->create($this->serviceManager);
        }

        return null;
    }
}
