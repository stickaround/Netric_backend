<?php

namespace NetricTest\EntityGroupings\DataMapper;

use Netric\Entity\Entity;
use Netric\EntityGroupings\DataMapper\EntityGroupingDataMapperInterface;
use PHPUnit\Framework\TestCase;
use Netric\Entity\ObjType\UserEntity;
use NetricTest\Bootstrap;
use Netric\EntityDefinition\ObjectTypes;

/**
 * Define common tests that will need to be run with all data mappers.
 *
 * In order to implement the unit tests, a datamapper test case just needs
 * to extend this class and create a getDataMapper class that returns the
 * datamapper to be tested
 */
abstract class AbstractDataMapperTests extends TestCase
{
    /**
     * Tenant account
     *
     * @var \Netric\Account\Account
     */
    protected $account = null;

    /**
     * Administrative user
     *
     * @var \Netric\User
     */
    protected $user = null;

    /**
     * Groups to clean up inside the groupings table
     *
     * @var groupId[]
     */
    protected $testObjectGroupings = [];

    /**
     * Setup each test
     */
    protected function setUp(): void
    {
        $this->account = Bootstrap::getAccount();
        $this->user = $this->account->getUser(null, UserEntity::USER_SYSTEM);
    }

    /**
     * Cleanup any test entities
     */
    protected function tearDown(): void
    {
        $dm = $this->getDataMapper();
        $groupings = $dm->getGroupingsByPath(ObjectTypes::CONTACT . "/groups", $this->account->getAccountId());

        // Cleanup the test groupings in groupings table
        foreach ($this->testObjectGroupings as $groupId) {
            $groupings->delete($groupId);
        }

        $dm->saveGroupings($groupings);
    }

    /**
     * Setup datamapper for the parent DataMapperTests class
     *
     * @return EntityGroupingDataMapperInterface
     */
    abstract protected function getDataMapper();


    /**
     * Test adding, removing and updating groups
     */
    public function testSaveGroupings()
    {
        $dm = $this->getDataMapper();

        $groupings = $dm->getGroupingsByPath(ObjectTypes::CONTACT . "/groups", $this->account->getAccountId());

        // Save new
        $newGroup = $groupings->create();
        $newGroup->name = "UTTEST DM::testSaveGroupings";
        $groupings->add($newGroup);
        $dm->saveGroupings($groupings);
        $group = $groupings->getByName($newGroup->name);
        $this->assertNotEquals($group->getGroupId(), "");
        $this->testObjectGroupings[] = $newGroup->id;

        // Save existing
        $name2 = "UTTEST DM::testSaveGroupings::edited";
        $group = $groupings->getByName($newGroup->name);
        $this->assertEquals($group->name, $newGroup->name);

        $group->name = $name2;
        $group->setDirty(true);
        $dm->saveGroupings($groupings);
        $gid = $group->getGroupId();

        unset($groupings);
        $groupings = $dm->getGroupingsByPath(ObjectTypes::CONTACT . "/groups", $this->account->getAccountId());
        $group = $groupings->getByGuidOrGroupId($gid);
        $this->assertEquals($name2, $group->name);

        // Test delete
        $groupings->delete($gid);
        $dm->saveGroupings($groupings);
        unset($groupings);
        $groupings = $dm->getGroupingsByPath(ObjectTypes::CONTACT . "/groups", $this->account->getAccountId());
        $this->assertFalse($groupings->getByGuidOrGroupId($gid));
    }

    /**
     * Test adding a grouping with an ID (like a negative number for a reserved grouping space)
     */
    public function testAddGroupingsWithId()
    {
        $dm = $this->getDataMapper();

        $groupings = $dm->getGroupingsByPath(ObjectTypes::CONTACT . "/groups", $this->account->getAccountId());

        // Save new grouping with a reserved id
        $newGroup = $groupings->create();
        $newGroup->id = -100;
        $newGroup->name = "UTTEST DM::testAddGroupingsWithId";
        $groupings->add($newGroup);
        $dm->saveGroupings($groupings);

        // Reload the groupings and make sure the above was saved
        $groupings = $dm->getGroupingsByPath(ObjectTypes::CONTACT . "/groups", $this->account->getAccountId());
        $group = $groupings->getByName($newGroup->name);
        $this->assertNotEmpty($group->getGroupId());
        $this->testObjectGroupings[] = $group->getGroupId();
    }

    /**
     * Loading groupings
     */
    public function testGetGroupingsByPath()
    {
        $dm = $this->getDataMapper();

        // No filter
        $groupings = $dm->getGroupingsByPath(ObjectTypes::CONTACT . "/groups", $this->account->getAccountId());

        // Delete just in case
        if ($groupings->getByName("UTEST.DM.testGetGroupings")) {
            $groupings->delete($groupings->getByName("UTEST.DM.testGetGroupings")->id);
            $dm->saveGroupings($groupings);
        }

        // Save new
        $newGroup = $groupings->create();
        $newGroup->name = "UTEST.DM.testGetGroupings";
        $groupings->add($newGroup);
        $dm->saveGroupings($groupings);
        $this->testObjectGroupings[] = $newGroup->id;

        $groupings = $dm->getGroupingsByPath(ObjectTypes::CONTACT . "/groups", $this->account->getAccountId());
        $group1 = $groupings->getByName($newGroup->name);
        $this->assertEquals($newGroup->name, $group1->name);

        // Add a subgroup
        $newGroup2 = $groupings->create();
        $newGroup2->name = "UTEST.DM.testGetGroupings2";
        $newGroup2->parentId = $group1->id;
        $groupings->add($newGroup2);
        $dm->saveGroupings($groupings);

        // set the group here to be cleaned later
        $this->testObjectGroupings[] = $newGroup2->id;

        unset($groupings);
        $groupings = $dm->getGroupingsByPath(ObjectTypes::CONTACT . "/groups", $this->account->getAccountId());
        $group2 = $groupings->getByPath($newGroup->name . "/" . $newGroup2->name);
        $this->assertEquals($newGroup2->name, $group2->name);
    }

    /**
     * Test entity has moved functionalty
     */
    public function testCommitIncrement()
    {
        $dm = $this->getDataMapper();

        // No filter grouping
        $groupings = $dm->getGroupingsByPath(ObjectTypes::CONTACT . "/groups", $this->account->getAccountId());

        // Save new
        $newGroup = $groupings->create();
        $newGroup->name = "UTEST.DM.testGetGroupings";
        $groupings->add($newGroup);
        $dm->saveGroupings($groupings);
        $oldCommitId = $groupings->getByName($newGroup->name)->commitId;
        $this->testObjectGroupings[] = $newGroup->id;
        $this->assertNotEquals(0, $oldCommitId);

        // Add another to increment commit id
        $newGroup2 = $groupings->create();
        $newGroup2->name = "UTEST.DM.testGetGroupings2";
        $groupings->add($newGroup2);
        $dm->saveGroupings($groupings);
        $newCommitId = $groupings->getByName($newGroup2->name)->commitId;
        $this->testObjectGroupings[] = $newGroup2->id;
        $this->assertNotEquals($oldCommitId, $newCommitId);

        // Reload and double check commitIDs
        $groupings = $dm->getGroupingsByPath(ObjectTypes::CONTACT . "/groups", $this->account->getAccountId());
        $oldCommitId = $groupings->getByName($newGroup->name)->commitId;
        $newCommitId = $groupings->getByName($newGroup2->name)->commitId;
        $this->assertNotEquals($oldCommitId, $newCommitId);
    }
}
