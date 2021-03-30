<?php

declare(strict_types=1);

namespace NetricTest\Account\InitData\Sets;

use Netric\Account\InitData\Sets\WorkerJobsInitData;
use Netric\Account\InitData\Sets\WorkerJobsInitDataFactory;
use PHPUnit\Framework\TestCase;

/**
 * @group integration
 */
class WorkerJobsInitDataFactoryTest extends TestCase
{
    /**
     * At a basic level, make sure we can run without throwing any exceptions
     */
    public function testSetInitialData()
    {
        $account = \NetricTest\Bootstrap::getAccount();
        $dataSet = $account->getServiceManager()->get(WorkerJobsInitDataFactory::class);
        $this->assertInstanceOf(WorkerJobsInitData::class, $dataSet);
    }
}
