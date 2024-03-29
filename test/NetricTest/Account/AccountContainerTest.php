<?php

namespace NetricTest\Account;

use Netric;
use PHPUnit\Framework\TestCase;
use Netric\Application\DataMapperInterface;
use Netric\Account\Account;
use Netric\Application\DataMapperFactory;
use Netric\Cache\CacheFactory;
use Netric\Account\AccountContainer;

/**
 * Test entity  loader class that is responsible for creating and initializing exisiting objects
 * @group integration
 */
class AccountIdentityMapperTest extends TestCase
{
    /**
     * Tennant account
     *
     * @var \Netric\Account\Account
     */
    private $account = null;

    /**
     * Identity mapper used for testing
     */
    private $mapper = null;

    /**
     * Cache interface
     *
     * @var \Netric\Cache\CacheInterface
     */
    private $cache = null;

    /**
     * Application datamapper
     *
     * @var DataMapperInterface
     */
    private $dataMapper = null;

    /**
     * Setup each test
     */
    protected function setUp(): void
    {
        $this->account = \NetricTest\Bootstrap::getAccount();
        $this->cache = $this->account->getServiceManager()->get(CacheFactory::class);
        $this->dataMapper = $this->account->getServiceManager()->get(DataMapperFactory::class);
        $this->mapper = new AccountContainer($this->dataMapper, $this->cache, $this->account->getApplication());
    }

    public function testLoadById()
    {
        $application = $this->account->getApplication();

        // First reset cache to make sure the mapper is setting it correctly
        $this->cache->delete("netric/account/" . $this->account->getAccountId());

        // Setup Reflection Methods
        $refIm = new \ReflectionObject($this->mapper);
        $loadFromCache = $refIm->getMethod("loadFromCache");
        $loadFromCache->setAccessible(true);
        $loadFromMemory = $refIm->getMethod("loadFromMemory");
        $loadFromMemory->setAccessible(true);
        $propCache = $refIm->getProperty("cache");
        $propCache->setAccessible(true);
        $propAppDm = $refIm->getProperty("appDm");
        $propAppDm->setAccessible(true);

        // Make sure cache initially returns false
        $args = [$this->account->getAccountId(), &$this->account];
        $this->assertFalse($loadFromCache->invokeArgs($this->mapper, $args));

        // Make sure memory initially returns false
        $args = [$this->account->getAccountId(), &$this->account];
        $this->assertFalse($loadFromMemory->invokeArgs($this->mapper, $args));

        // Test loading existing account which should cache it
        $testAccount = $this->mapper->loadById($this->account->getAccountId(), $application);
        $this->assertEquals($this->account->getAccountId(), $testAccount->getAccountId());

        // Make sure cache returns true
        $args = [$this->account->getAccountId(), &$this->account];
        $this->assertTrue($loadFromCache->invokeArgs($this->mapper, $args));

        // Make sure memory returns true
        $args = [$this->account->getAccountId(), &$this->account];
        $this->assertNotNull($loadFromMemory->invokeArgs($this->mapper, $args));

        // Unset the datamapper so we can test memory and cache
        $propAppDm->setValue($this->mapper, null);

        // Make sure we are loading from memory by disabling the cache
        $propCache->setValue($this->mapper, null);
        $testAccount = $this->mapper->loadById($this->account->getAccountId(), $application);
        $this->assertEquals($this->account->getAccountId(), $testAccount->getAccountId());
        $propCache->setValue($this->mapper, $this->cache); // re-enable

        // Make sure the cache is working by disabling the loadedAccounts
        $loadedAccounts = $refIm->getProperty("loadedAccounts");
        $loadedAccounts->setAccessible(true);
        $loadedAccounts->setValue($this->mapper, null);
        $testAccount = $this->mapper->loadById($this->account->getAccountId(), $application);
        $this->assertEquals($this->account->getAccountId(), $testAccount->getAccountId());
    }

    public function testLoadByName()
    {
        $application = $this->account->getApplication();

        // First reset cache to make sure the mapper is setting it correctly
        $this->cache->delete("netric/account/nametoidmap/" . $this->account->getName());

        // Test loading existing account which should cache it
        $testAccount = $this->mapper->loadByName($this->account->getName(), $application);
        $this->assertEquals($this->account->getAccountId(), $testAccount->getAccountId());

        // Check local memory map
        $propNameToIdMap = new \ReflectionProperty($this->mapper, "nameToIdMap");
        $propNameToIdMap->setAccessible(true);
        $vals = $propNameToIdMap->getValue($this->mapper);
        $this->assertEquals($this->account->getAccountId(), $vals[$this->account->getName()]);

        // Unset the datamapper so we can test memory and cache
        $propAppDm = new \ReflectionProperty($this->mapper, "appDm");
        $propAppDm->setAccessible(true);
        $propAppDm->setValue($this->mapper, null);

        // Make sure we are loading from memory by disabling the cache
        $propCache = new \ReflectionProperty($this->mapper, "cache");
        $propCache->setAccessible(true);
        $propCache->setValue($this->mapper, null);
        $testAccount = $this->mapper->loadByName($this->account->getName(), $application);
        $this->assertEquals($this->account->getAccountId(), $testAccount->getAccountId());

        // Make sure the cache is working by disabling local memory cache
        $propCache->setValue($this->mapper, $this->cache);
        $propNameToIdMap->setValue($this->mapper, null);
        $testAccount = $this->mapper->loadByName($this->account->getName(), $application);
        $this->assertEquals($this->account->getAccountId(), $testAccount->getAccountId());
    }

    public function testDeleteAccount()
    {
        $application = $this->account->getApplication();

        // Make sure we don't have a test account left over from past failures
        $deleteAccount = new Account($application);
        if ($this->dataMapper->getAccountByName("unit_test_im", $deleteAccount)) {
            $this->mapper->deleteAccount($deleteAccount);
        }

        // Create a test account directly in the database
        $accountId = $this->dataMapper->createAccount(
            "unit_test_im"
        );

        // Load the test account (this will cache it)
        $testAccount = $this->mapper->loadById($accountId, $application);

        // Re-load by name which will cache the name-to-id maps
        $testAccountAgain = $this->mapper->loadByName($testAccount->getName(), $application);

        // Now delete the account which should purge all caches
        $this->assertTrue($this->mapper->deleteAccount($testAccount));

        // Make sure loadFromCache returns false
        $loadFromCache = new \ReflectionMethod($this->mapper, "loadFromCache");
        $loadFromCache->setAccessible(true);
        $args = [$testAccount->getAccountId(), &$this->account];
        $this->assertFalse($loadFromCache->invokeArgs($this->mapper, $args));

        // Make sure loadFromMemory returns false
        $loadFromMemory = new \ReflectionMethod($this->mapper, "loadFromMemory");
        $loadFromMemory->setAccessible(true);
        $this->assertFalse($loadFromMemory->invokeArgs($this->mapper, [$testAccount->getAccountId()]));

        // Check local memory map for id to name
        $propNameToIdMap = new \ReflectionProperty($this->mapper, "nameToIdMap");
        $propNameToIdMap->setAccessible(true);
        $vals = $propNameToIdMap->getValue($this->mapper);
        $this->assertFalse(isset($vals[$this->account->getName()]));
    }

    public function testCreateAccount()
    {
        $application = $this->account->getApplication();

        // Make sure we don't have a test account left over from past failures
        $deleteAccount = new Account($application);
        if ($this->dataMapper->getAccountByName("unit_test_im", $deleteAccount)) {
            $this->mapper->deleteAccount($deleteAccount);
        }

        // Test creating a new account
        $accountId = $this->mapper->createAccount('unit_test_im');
        $this->assertNotEquals(0, $accountId);

        // Cleanup
        $this->dataMapper->deleteAccount($accountId);
    }
}
