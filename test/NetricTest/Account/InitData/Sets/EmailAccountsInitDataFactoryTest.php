<?php

declare(strict_types=1);

namespace NetricTest\Account\InitData\Sets;

use Netric\Account\InitData\Sets\EmailAccountsInitData;
use Netric\Account\InitData\Sets\EmailAccountsInitDataFactory;
use PHPUnit\Framework\TestCase;

/**
 * @group integration
 */
class EmailAccountsInitDataFactoryTest extends TestCase
{
    /**
     * At a basic level, make sure we can run without throwing any exceptions
     */
    public function testSetInitialData()
    {
        $account = \NetricTest\Bootstrap::getAccount();
        $dataSet = $account->getServiceManager()->get(EmailAccountsInitDataFactory::class);
        $this->assertInstanceOf(EmailAccountsInitData::class, $dataSet);
    }
}
