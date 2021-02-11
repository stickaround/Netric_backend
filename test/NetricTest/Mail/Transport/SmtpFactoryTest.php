<?php

/**
 * Test the Smtp service factory
 */

namespace NetricTest\Mail\Transport;

use Netric\Mail\Transport\SmtpFactory;
use PHPUnit\Framework\TestCase;
use Netric\Settings\SettingsFactory;
use Netric\Mail\Transport\Smtp;
use Netric\Account\Account;
use NetricTest\Bootstrap;

/**
 * @group integration
 */
class SmtpFactoryTest extends TestCase
{
    /**
     * Reference to account running for unit tests
     */
    private $account;

    protected function setUp(): void
    {
        // Create a new test account to test the settings
        $this->account = Bootstrap::getAccount();
    }

    public function testCreateService()
    {
        $sm = $this->account->getServiceManager();
        $this->assertInstanceOf(
            Smtp::class,
            $sm->get(SmtpFactory::class)
        );
    }

    public function testCreateServiceWithSettings()
    {
        $testHost = 'mail.limited.ltd';
        $testPort = 33;
        $testUser = 'testuser';
        $testPassword = 'password';

        $this->account = Bootstrap::getAccount();
        $sm = $this->account->getServiceManager();
        $settings = $sm->get(SettingsFactory::class);
        $settings->set('email/smtp_host', $testHost, $this->account->getAccountId());
        $settings->set('email/smtp_port', $testPort, $this->account->getAccountId());
        $settings->set('email/smtp_user', $testUser, $this->account->getAccountId());
        $settings->set('email/smtp_password', $testPassword, $this->account->getAccountId());

        $smtpFactory = new SmtpFactory();
        $transport = $smtpFactory->createService($sm);

        $this->assertInstanceOf(
            Smtp::class,
            $transport
        );

        $options = $transport->getOptions();
        $this->assertEquals($testHost, $options->getHost());
        $this->assertEquals($testPort, $options->getPort());
        $this->assertEquals('login', $options->getConnectionClass());
        $this->assertEquals(
            ['username' => $testUser, 'password' => $testPassword],
            $options->getConnectionConfig()
        );
    }
}
