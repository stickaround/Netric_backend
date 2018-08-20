<?php

/**
 * Test entity groupings loader class that is responsible for creating and initializing exisiting objects
 */
namespace NetricTest\EntityGroupings;

use PHPUnit\Framework\TestCase;
use Netric\Entity\ObjType\UserEntity;
use Netric\EntityGroupings\DataMapper\EntityGroupingDataMapperFactory;

class EntityGroupingStateManagerTest extends TestCase
{
    /**
     * Tenant account
     *
     * @var \Netric\Account\Account
     */
    private $account = null;

    /**
     * Setup each test
     */
    protected function setUp()
    {
        $this->account = \NetricTest\Bootstrap::getAccount();
    }

    /**
     * Test loading an object definition
     */
    public function testGet()
    {
        $dm = $this->account->getServiceManager()->get('Netric\EntityGroupings\DataMapper\EntityGroupingDataMapper');
        
        // Create test group
        $groupings = $dm->getGroupings("customer", "groups");
        $newGroup = $groupings->create();
        $newGroup->name = "uttest-eg-loader-get";
        $groupings->add($newGroup);
        $dm->saveGroupings($groupings);

        
        // Load through loader
        $loader = $this->account->getServiceManager()->get("EntityGroupings_Loader");
        $loader->clearCache("customer", "groups");
        
        // Use the loader to get the object
        $grp = $loader->get("customer", "groups")->getByName($newGroup->name);
        $this->assertNotNull($grp);
        $this->assertEquals($newGroup->name, $grp->name);

        // Test to see if the isLoaded function indicates the entity has been loaded and cached locally
        $refIm = new \ReflectionObject($loader);
        $isLoaded = $refIm->getMethod("isLoaded");
        $isLoaded->setAccessible(true);
        $this->assertTrue($isLoaded->invoke($loader, "customer", "groups"));

        // TODO: Test to see if it is cached
        /*
        $refIm = new \ReflectionObject($loader);
        $getCached = $refIm->getMethod("getCached");
        $getCached->setAccessible(true);
        $this->assertTrue(is_array($getCached->invoke($loader, "customer", $cid)));
         * *
         */

        // Cleanup
        $groups = $loader->get("customer", "groups");
        $grp = $groups->getByName($newGroup->name);
        $groups->delete($grp->id);
        $groups->save();
    }

    /**
     * Test loading an object definition
     */
    public function testGetFiltered()
    {
        // Create test group manually
        $dm = $this->account->getServiceManager()->get(EntityGroupingDataMapperFactory::class);
        $systemUser = $this->account->getUser(UserEntity::USER_SYSTEM);
        $groupings = $dm->getGroupings("note", "groups", array("user_id" => $systemUser->getId()));
        $newGroup = $groupings->create();
        $newGroup->name = "utttest";
        $newGroup->user_id = $systemUser->getId();
        $groupings->add($newGroup);
        $dm->saveGroupings($groupings);

        // Load through loader
        $loader = $this->account->getServiceManager()->get("EntityGroupings_Loader");

        // Use the loader to get private groups
        $groupings = $loader->get("note", "groups", array("user_id" => $systemUser->getId()));
        $grp = $groupings->getByName($newGroup->name);
        $this->assertNotNull($grp->id);
        $this->assertNotNull($grp->user_id);

        // Cleanup
        $groupings->delete($grp->id);
        $groupings->save();
    }
}