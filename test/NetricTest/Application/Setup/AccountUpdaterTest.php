<?php

/**
 * Test account setup functions
 */

namespace NetricTest\Application\Setup;

use Netric\Account\Account;
use Netric\Application\Application;
use Netric\Application\Setup\AccountUpdater;
use Netric\Application\Setup\AccountUpdaterFactory;
use Netric\Account\AccountContainer;
use Netric\Application\Setup\Setup;
use Netric\Settings\SettingsFactory;
use PHPUnit\Framework\TestCase;
use NetricTest\Bootstrap;

/**
 * @group integration
 */
class AccountUpdaterTest extends TestCase
{
    /**
     * Reference to account running for unit tests
     *
     * @var \Netric\Account\Account
     */
    private $account = null;

    /**
     * This will be used to run updates on an account
     */
    protected AccountUpdater $accountUpdater;

    /**
     * Test account name
     *
     * @var const
     */
    const TEST_ACCOUNT_NAME = 'ut_acct_updater';

    protected function setUp(): void
    {
        $this->account = Bootstrap::getAccount();

        // Cleanup if there's any left-overs from a failed test
        $application = $this->account->getApplication();
        $accountToDelete = $application->getAccount(null, self::TEST_ACCOUNT_NAME);
        if ($accountToDelete) {
            $application->deleteAccount($accountToDelete->getName());
        }

        $this->accountUpdater = $this->account->getServiceManager()->get(AccountUpdaterFactory::class);
    }

    protected function tearDown(): void
    {
        // Cleanup if there's any left-overs from a failed test
        $application = $this->account->getApplication();
        $accountToDelete = $application->getAccount(null, self::TEST_ACCOUNT_NAME);
        if ($accountToDelete) {
            $application->deleteAccount($accountToDelete->getName());
        }
    }

    public function testGetLatestVersion()
    {
        // Make sure we got something other than the default
        $this->assertNotEquals("0.0.0", $this->accountUpdater->getLatestVersion($this->account));
    }

    public function testRunOnceUpdates()
    {
        $application = $this->account->getApplication();

        // Create a new test account
        $account = $application->createAccount(
            self::TEST_ACCOUNT_NAME,
            'test',
            "test@test.com",
            "password"
        );
        $settings = $account->getServiceManager()->get(SettingsFactory::class);

        // Run test updates in TestAssets/UpdateScripts which should result in 1.1.1
        $settings->set("system/schema_version", "0.0.0", $account->getAccountId());
        $accountUpdater = $account->getServiceManager()->get(AccountUpdaterFactory::class);
        $accountUpdater->setScriptsRootPath(__DIR__ . "/TestAssets/UpdateScripts");        
        $accountUpdater->runOnceUpdates($account);

        // Make sure it all ran
        $this->assertEquals("1.1.1", $settings->get("system/schema_version", $account->getAccountId()));
        // The update script - TestAssets/once/001/001/001.php changes the description
        $this->assertEquals("edited", $account->getDescription());
    }

    public function testRunAlwaysUpdates()
    {
        $application = $this->account->getApplication();

        // Create a new test account
        $account = $application->createAccount(
            self::TEST_ACCOUNT_NAME,
            'test',
            "test@test.com",
            "password"
        );

        // Run test updates in TestAssets/UpdateScripts which should result in 1.1.1
        $accountUpdater = $account->getServiceManager()->get(AccountUpdaterFactory::class);
        $accountUpdater->setScriptsRootPath(__DIR__ . "/TestAssets/UpdateScripts");
        $accountUpdater->runAlwaysUpdates($account);

        // An always update will set the description to always
        $this->assertEquals("always", $account->getDescription());
    }

    /**
     * Test setting an account to the latest updates version
     *
     * @return void
     */
    public function testSetCurrentAccountToLatestVersion()
    {
        $application = $this->account->getApplication();

        // Create a new test account
        $account = $application->createAccount(
            self::TEST_ACCOUNT_NAME,
            'test',
            "test@test.com",
            "password"
        );

        // Run test updates in TestAssets/UpdateScripts which should result in 1.1.1
        $accountUpdater = $account->getServiceManager()->get(AccountUpdaterFactory::class);
        $accountUpdater->setScriptsRootPath(__DIR__ . "/TestAssets/UpdateScripts");

        $latestVersion = $accountUpdater->getLatestVersion($account);
        $accountUpdater->setCurrentAccountToLatestVersion($account);
        $this->assertEquals($latestVersion, $accountUpdater->getCurrentVersion());
    }
}
