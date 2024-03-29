<?php
/**
 * Test the RecurrenceDataMapperFactoryTest service factory
 */

namespace NetricTest\Entity\Recurrence;

use Netric;
use Netric\Entity\Recurrence\RecurrenceDataMapperFactory;
use Netric\Entity\Recurrence\RecurrenceRdbDataMapper;
use PHPUnit\Framework\TestCase;
use NetricTest\Bootstrap;

class RecurrenceDataMapperFactoryTest extends TestCase
{
    public function testCreateService()
    {
        $account = Bootstrap::getAccount();
        $sm = $account->getServiceManager();
        $dm = $sm->get(RecurrenceDataMapperFactory::class);
        $this->assertInstanceOf(RecurrenceRdbDataMapper::class, $dm);
    }
}
