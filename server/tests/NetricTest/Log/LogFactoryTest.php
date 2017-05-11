<?php

namespace NetricTest\Log;

use Netric;

use PHPUnit_Framework_TestCase;

class LogFactoryTest extends PHPUnit_Framework_TestCase
{
    public function testCreateService()
    {
        $account = \NetricTest\Bootstrap::getAccount();
        $sm = $account->getServiceManager();

        $this->assertInstanceOf(
            'Netric\Log\Log',
            $sm->get('Log')
        );

        $this->assertInstanceOf(
            'Netric\Log\Log',
            $sm->get('Netric\Log\Log')
        );
    }
}