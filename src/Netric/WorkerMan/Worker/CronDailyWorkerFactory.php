<?php

declare(strict_types=1);

namespace Netric\WorkerMan\Worker;

use Netric\Account\Billing\AccountBillingServiceFactory;
use Netric\Log\LogFactory;
use Netric\ServiceManager\ServiceLocatorInterface;

/**
 * Construct worker called each minute like a cron job
 */
class CronDailyWorkerFactory
{
    /**
     * Entity creation factory
     *
     * @param ServiceLocatorInterface $serviceLocator For injecting dependencies
     * @return TestWorker
     */
    public function create(ServiceLocatorInterface $serviceLocator)
    {
        $accountBilllingService = $serviceLocator->get(AccountBillingServiceFactory);
        $log = $serviceLocator->get(LogFactory::class);

        return new CronDailyWorker($accountBilllingService, $log);
    }
}
