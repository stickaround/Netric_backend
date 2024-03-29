<?php

/**
 * Define common tests that will need to be run with all data mappers.
 *
 * In order to implement the unit tests, a datamapper test case just needs
 * to extend this class and create a getDataMapper class that returns the
 * datamapper to be tested
 */

namespace NetricTest\Entity\DataMapper;

use Netric;
use Netric\Entity\Entity;
use Netric\Entity\DataMapper\EntityDataMapperInterface;
use Netric\EntityGroupings\DataMapper\EntityGroupingDataMapperInterface;
use Netric\Entity\Recurrence\RecurrencePattern;
use PHPUnit\Framework\TestCase;
use Netric\Entity\EntityLoaderFactory;
use NetricTest\Bootstrap;
use Netric\Entity\ObjType\UserEntity;
use Netric\EntityGroupings\DataMapper\EntityGroupingDataMapperFactory;
use Netric\EntityDefinition\ObjectTypes;
use Netric\Entity\Recurrence\RecurrenceDataMapperFactory;
use Netric\Db\Relational\RelationalDbFactory;
use DateTime;

abstract class DmTestsAbstract extends TestCase
{
    /**
     * Tennant account
     *
     * @var \Netric\Account\Account
     */
    protected $account = null;

    /**
     * Administrative user
     *
     */
    protected UserEntity $user;

    /**
     * Test entities created that needt to be cleaned up
     *
     * @var EntityInterface
     */
    protected $testEntities = [];

    /**
     * DataMapper for saving and loading groupings
     *
     * @var EntityGroupingDataMapperInterface
     */
    private $groupingDataMapper = null;

    /**
     * Setup each test
     */
    protected function setUp(): void
    {
        $this->account = Bootstrap::getAccount();
        $this->user = $this->account->getUser(null, UserEntity::USER_SYSTEM);
        $this->groupingDataMapper = $this->account->getServiceManager()->get(EntityGroupingDataMapperFactory::class);
    }

    /**
     * Cleanup any test entities we created
     */
    protected function tearDown(): void
    {
        $dm = $this->getDataMapper();
        foreach ($this->testEntities as $entity) {
            $dm->delete($entity, $this->account->getAuthenticatedUser());
        }
    }

    /**
     * Setup datamapper for the parent DataMapperTests class
     *
     * @return EntityDataMapperInterface
     */
    abstract protected function getDataMapper();

    /**
     * Utility function to populate custome entity for testing
     *
     * @return Entity
     */
    protected function createCustomer()
    {
        $customer = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::CONTACT, $this->account->getAccountId());
        // text
        $customer->setValue("name", "Entity_DataMapperTests");
        // bool
        $customer->setValue("is_nocall", true);
        // object
        $customer->setValue("owner_id", $this->user->getEntityId(), $this->user->getName());
        // object_multi
        // timestamp
        $contactedTime = mktime(0, 0, 0, 12, 1, 2013);
        $customer->setValue("last_contacted", $contactedTime);

        return $customer;
    }

    /**
     * Test loading an object by id and putting it into cache
     */
    public function testGetById()
    {
        $dm = $this->getDataMapper();
        if (!$dm) {
            // Do not run if we don't have a datamapper to work with
            $this->assertTrue(true);
            return;
        }

        // Create a few test groups
        $groupingsStat = $this->groupingDataMapper->getGroupingsByPath(ObjectTypes::CONTACT . "/status_id", $this->account->getAccountId());
        $statGrp = $groupingsStat->getByName("Unit Test Status");
        if (!$statGrp) {
            $statGrp = $groupingsStat->create("Unit Test Status", $this->account->getAccountId());
        }
        $groupingsStat->add($statGrp);
        $this->groupingDataMapper->saveGroupings($groupingsStat);

        $groupingsGroups = $this->groupingDataMapper->getGroupingsByPath(ObjectTypes::CONTACT . "/groups", $this->account->getAccountId());
        $groupsGrp = $groupingsGroups->getByName("Unit Test Group");
        if (!$groupsGrp) {
            $groupsGrp = $groupingsGroups->create("Unit Test Group", $this->account->getAccountId());
        }
        $groupingsGroups->add($groupsGrp);
        $this->groupingDataMapper->saveGroupings($groupingsGroups);

        // Create an entity and initialize values
        $customer = $this->createCustomer();
        // fkey
        $customer->setValue("status_id", $statGrp->getGroupId(), $statGrp->getName());
        // fkey_multi - groups
        $customer->addMultiValue("groups", $groupsGrp->getGroupId(), $groupsGrp->getName());
        // Cache returned time
        $contactedTime = $customer->getValue("last_contacted");
        $cid = $dm->save($customer, $this->user);

        // Queue for cleanup
        $this->testEntities[] = $customer;

        // Load the object through the loader which should cache it
        $ent = $dm->getEntityById($cid, $this->account->getAccountId());
        $this->assertEquals($ent->getEntityId(), $cid);
        $this->assertEquals($ent->getValue("name"), "Entity_DataMapperTests");
        $this->assertTrue($ent->getValue("is_nocall"));
        $this->assertEquals($ent->getValue("owner_id"), $this->user->getEntityId());
        $this->assertEquals($ent->getValueName("owner_id"), $this->user->getName());
        $this->assertEquals($ent->getValue("status_id"), $statGrp->getGroupId());
        $this->assertEquals($ent->getValueName("status_id"), "Unit Test Status");
        $this->assertEquals($ent->getValue("groups"), [$groupsGrp->getGroupId()]);
        $this->assertEquals($ent->getValueName("groups"), "Unit Test Group");
        $this->assertEquals($ent->getValue("last_contacted"), $contactedTime);

        // Cleanup groupings
        $groupingsStat->delete($statGrp->getGroupId());
        $this->groupingDataMapper->saveGroupings($groupingsStat);

        $groupingsGroups->delete($groupsGrp->getGroupId());
        $this->groupingDataMapper->saveGroupings($groupingsGroups);
    }

    /**
     * Test loading an object by guid
     */
    public function testGetByGuid()
    {
        $dm = $this->getDataMapper();

        // Create an entity and initialize values
        $customer = $this->createCustomer();
        $customer->setValue('name', 'tester');
        $dm->save($customer, $this->user);
        $entityId = $customer->getEntityId();

        // Queue for cleanup
        $this->testEntities[] = $customer;

        // Load the entity by guid (no need for obj_type)
        $loadedCustomer = $dm->getEntityById($entityId, $this->account->getAccountId());
        $this->assertEquals($customer->getEntityId(), $loadedCustomer->getEntityId());
    }

    /**
     * Test loading an object by id and putting it into cache
     */
    public function testSave()
    {
        $dm = $this->getDataMapper();

        // Create a few test groups
        $groupingsStat = $this->groupingDataMapper->getGroupingsByPath(ObjectTypes::CONTACT . "/status_id", $this->account->getAccountId());
        if (!$groupingsStat->getByName("Unit Test Status")) {
            $statGrp = $groupingsStat->create("Unit Test Status", $this->account->getAccountId());
            $groupingsStat->add($statGrp);
            $this->groupingDataMapper->saveGroupings($groupingsStat);
        }

        $groupingsGroups = $this->groupingDataMapper->getGroupingsByPath(ObjectTypes::CONTACT . "/groups", $this->account->getAccountId());
        $groupsGrp = $groupingsGroups->create("Unit Test Group", $this->account->getAccountId());
        $groupingsGroups->add($groupsGrp);
        $this->groupingDataMapper->saveGroupings($groupingsGroups);

        // Create an entity and initialize values
        $customer = $this->createCustomer();
        // fkey
        $customer->setValue("status_id", $statGrp->getGroupId(), $statGrp->getName());
        // fkey_multi - groups
        $customer->addMultiValue("groups", $groupsGrp->getGroupId(), $groupsGrp->getName());
        // Cache returned time
        $contactedTime = $customer->getValue("last_contacted");

        // Try to set ts_entered, it should not use the pre-set value, instead it will use the server time.
        $customer->setValue("ts_entered", "January 01, 2000");

        $cid = $dm->save($customer, $this->user);
        $this->assertNotEquals(false, $cid);

        // Queue for cleanup
        $this->testEntities[] = $customer;

        // Load the object through the loader which should cache it
        $ent = $dm->getEntityById($cid, $this->account->getAccountId());
        $this->assertEquals($ent->getEntityId(), $cid);
        $this->assertEquals($ent->getValue("name"), "Entity_DataMapperTests");
        $this->assertTrue($ent->getValue("is_nocall"));
        $this->assertEquals($ent->getValue("owner_id"), $this->user->getEntityId());
        $this->assertEquals($ent->getValueName("owner_id"), $this->user->getName());
        $this->assertEquals($ent->getValue("status_id"), $statGrp->getGroupId());
        $this->assertEquals($ent->getValueName("status_id"), $statGrp->getName());
        $this->assertEquals($ent->getValue("groups"), [$groupsGrp->getGroupId()]);
        $this->assertEquals($ent->getValueName("groups"), $groupsGrp->getName());
        $this->assertEquals($ent->getValue("last_contacted"), $contactedTime);

        // ts_entered should not be equal to january 01, 2000
        $this->assertNotEquals(date("m-d-Y", $ent->getValue("ts_entered")), "01-01-2000");
        
        // Cleanup groupings
        $groupingsStat->delete($statGrp->getGroupId());
        $this->groupingDataMapper->saveGroupings($groupingsStat);
        $groupingsGroups->delete($groupsGrp->getGroupId());
        $this->groupingDataMapper->saveGroupings($groupingsGroups);
    }


    /**
     * Make sure that saving twice on the same entity results in the same id
     * @group testSave
     */
    public function testSaveTwiceSameId()
    {
        $dm = $this->getDataMapper();

        // Create an entity and initialize values
        $cmsSite = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::SITE, $this->account->getAccountId());
        $cmsSite->setValue("name", "test site");
        $cid = $dm->save($cmsSite, $this->user);

        // Queue for cleanup
        $this->testEntities[] = $cmsSite;

        // Save the entity again and make sure the IDs are the same
        $cmsSite->setValue("name", 'utest-edited');

        // Also update try to update ts_updated with an old date
        $cmsSite->setValue("ts_updated", "January 01, 2000");

        $savedAgainCid = $dm->save($cmsSite, $this->user);
        $this->assertEquals($cid, $savedAgainCid);
        $this->assertEquals($cid, $cmsSite->getEntityId());

        // ts_updated should use the server time when updating an entity and not the pre-set value january 01, 2000
        $this->assertNotEquals(date("m-d-Y", $cmsSite->getValue("ts_updated")), "01-01-2000");

        // And finally soft-delete and once again assure the IDs are unchanged
        $dm->delete($cmsSite, $this->account->getAuthenticatedUser());
        $this->assertEquals($cid, $cmsSite->getEntityId());
    }

    public function testSaveClearMultiVal()
    {
        $dm = $this->getDataMapper();

        // Create a few test groups
        $groupingsStat = $this->groupingDataMapper->getGroupingsByPath(ObjectTypes::CONTACT . "/status_id", $this->account->getAccountId());
        $statGrp = $groupingsStat->create("Unit Test Status");
        $groupingsStat->add($statGrp);
        $this->groupingDataMapper->saveGroupings($groupingsStat);

        $groupingsGroups = $this->groupingDataMapper->getGroupingsByPath(ObjectTypes::CONTACT . "/groups", $this->account->getAccountId());
        $groupsGrp = $groupingsGroups->create("Unit Test Group");
        $groupingsGroups->add($groupsGrp);
        $this->groupingDataMapper->saveGroupings($groupingsGroups);

        // Create an entity and initialize values
        $customer = $this->createCustomer();
        $customer->addMultiValue("groups", $groupsGrp->getGroupId(), $groupsGrp->getName());
        // Cache returned time
        $cid = $dm->save($customer, $this->user);
        $this->assertNotEquals(false, $cid);

        // Now clear multi-vals
        $customer->clearMultiValues("groups");
        $cid = $dm->save($customer, $this->user);

        // Queue for cleanup
        $this->testEntities[] = $customer;

        // Load the object through the loader which should cache it
        $ent = $dm->getEntityById($cid, $this->account->getAccountId());

        $this->assertEquals([], $ent->getValue("groups"));
        $this->assertEquals([], $ent->getValueNames("groups"));
        $this->assertEquals('', $ent->getValueName("groups"));

        // Cleanup groupings
        $groupingsStat->delete($statGrp->getGroupId());
        $this->groupingDataMapper->saveGroupings($groupingsStat);
        $groupingsGroups->delete($groupsGrp->getGroupId());
        $this->groupingDataMapper->saveGroupings($groupingsGroups);
    }

    /**
     * Make sure the guid is set for a new entity
     */
    public function testSetGlobalId()
    {
        $dm = $this->getDataMapper();

        // Create an entity and initialize values
        $cmsSite = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::SITE, $this->account->getAccountId());
        $cmsSite->setValue("name", "test site");
        $dm->save($cmsSite, $this->user);

        // Queue for cleanup
        $this->testEntities[] = $cmsSite;

        // Make sure the guid was set when saved
        $this->assertNotEmpty($cmsSite->getEntityId());
    }

    /**
     * Test delete
     */
    public function testDelete()
    {
        $dm = $this->getDataMapper();
        if (!$dm) {
            return;
        }

        // First test a custom table object
        // ------------------------------------------------------------------------

        // Create a test customer to delete
        $customer = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::CONTACT, $this->account->getAccountId());
        $customer->setValue("name", "Entity_DataMapperTests");
        $cid = $dm->save($customer, $this->user);
        $this->assertNotEquals(false, $cid);

        // Test soft delete first
        $ret = $dm->archive($customer, $this->account->getAuthenticatedUser());
        $this->assertTrue($ret);

        // Reload and test if flagged but still in database
        $customer = $dm->getEntityById($cid, $this->account->getAccountId());
        $this->assertTrue($ret);
        $this->assertEquals(true, $customer->isArchived());

        // Now delete and make sure the object cannot be reloaded
        $ret = $dm->delete($customer, $this->account->getAuthenticatedUser());
        $this->assertTrue($ret);
        $customer = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::CONTACT, $this->account->getAccountId());
        $this->assertNull($dm->getEntityById($cid, $this->account->getAccountId())); // Not found
    }

    /**
     * Test entity has moved functionalty
     */
    public function testSetEntityMovedTo()
    {
        $dm = $this->getDataMapper();
        if (!$dm) {
            return;
        }

        // Create first entity
        $customer = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::CONTACT, $this->account->getAccountId());
        $customer->setValue("name", "testSetEntityMovedTo");
        $oid1 = $dm->save($customer, $this->user);

        // Queue for cleanup
        $this->testEntities[] = $customer;

        // Create second entity
        $customer2 = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::CONTACT, $this->account->getAccountId());
        $customer2->setValue("name", "testSetEntityMovedTo");
        $oid2 = $dm->save($customer2, $this->user);

        // Queue for cleanup
        $this->testEntities[] = $customer2;

        // Set moved to
        $def = $customer->getDefinition();
        $ret = $dm->setEntityMovedTo(
            $customer->getEntityId(),
            $customer2->getEntityId(),
            $this->account->getAccountId()
        );
        $this->assertTrue($ret);
    }

    /**
     * Test entity has moved functionalty
     */
    public function testEntityHasMoved()
    {
        $dm = $this->getDataMapper();
        if (!$dm) {
            return;
        }

        // Create first entity
        $customer = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::CONTACT, $this->account->getAccountId());
        $customer->setValue("name", "testSetEntityMovedTo");
        $oid1 = $dm->save($customer, $this->user);

        // Queue for cleanup
        $this->testEntities[] = $customer;

        // Create second entity
        $customer2 = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::CONTACT, $this->account->getAccountId());
        $customer2->setValue("name", "testSetEntityMovedTo");
        $oid2 = $dm->save($customer2, $this->user);

        // Queue for cleanup
        $this->testEntities[] = $customer2;

        // Set moved to
        $def = $customer->getDefinition();
        $ret = $dm->setEntityMovedTo(
            $customer->getEntityId(),
            $customer2->getEntityId(),
            $this->account->getAccountId()
        );

        // Get access to protected entityHasMoved with reflection object
        $refIm = new \ReflectionObject($dm);
        $entityHasMoved = $refIm->getMethod("entityHasMoved");
        $entityHasMoved->setAccessible(true);
        $movedTo = $entityHasMoved->invoke($dm, $oid1, $this->account->getAccountId());

        // Now make sure the movedTo works
        $this->assertEquals($oid2, $movedTo);
    }

    /**
     * Test revisions
     */
    public function testGetRevisions()
    {
        $dm = $this->getDataMapper();

        // Save first time
        $customer = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::CONTACT, $this->account->getAccountId());
        $customer->setValue("name", "First");
        $cid = $dm->save($customer, $this->user);
        $this->testEntities[] = $customer;
        $this->assertEquals(1, $customer->getValue("revision"));

        // Change value and set again
        $customer->setValue("name", "Second");
        $dm->save($customer, $this->user);
        $rev1 = $customer->getValue("revision");
        $this->assertEquals(2, $customer->getValue("revision"));

        // Get the revisions and make sure old value is stored
        $revisions = $dm->getRevisions($cid, $this->account->getAccountId());
        $this->assertEquals("First", $revisions[1]->getValue("name"));
        $this->assertEquals("Second", $revisions[2]->getValue("name"));

        // Delete and make sure revisions got deleted
        $dm->delete($customer, $this->account->getAuthenticatedUser());
        $this->assertEquals(0, count($dm->getRevisions($cid, $this->account->getAccountId())));
    }

    /**
     * Test skip revisions if the definition has saveRevisions set to false
     */
    public function testSaveRevisionsSetting()
    {
        $dm = $this->getDataMapper();

        // Save first time
        $customer = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::CONTACT, $this->account->getAccountId());
        // Set saveRevisions to false
        $customer->getDefinition()->storeRevisions = false;
        $customer->setValue("name", "First");
        $cid = $dm->save($customer, $this->user);
        $this->testEntities[] = $customer;
        $this->assertEquals(1, $customer->getValue("revision"));

        // Make sure revisions got deleted
        $this->assertEquals(0, count($dm->getRevisions($cid, $this->account->getAccountId())));

        // Turn back on and save changes
        $customer->getDefinition()->storeRevisions = true;
        $customer->setValue("name", "Second");
        $dm->save($customer, $this->user);

        // Get the revisions and make sure old value is stored
        $revisions = $dm->getRevisions($cid, $this->account->getAccountId());
        $this->assertEquals("Second", $revisions[2]->getValue("name"));

        // Cleanup
        $dm->delete($customer, $this->account->getAuthenticatedUser());
    }

    /**
     * Test entity has moved functionalty
     */
    public function testCommitIncrement()
    {
        $dm = $this->getDataMapper();

        // Save first time
        $customer = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::CONTACT, $this->account->getAccountId());

        // Set saveRevisions to false
        $customer->setValue("name", "testCommitIncrement First");
        $cid = $dm->save($customer, $this->user);
        $firstCommitId = $customer->getValue("commit_id");
        $this->testEntities[] = $customer;
        $this->assertNotEmpty($firstCommitId);

        // Save again which should change the comit id to the new head
        $customer->setValue("name", "testCommitIncrement Second");
        $dm->save($customer, $this->user);
        $secondCommitId = $customer->getValue("commit_id");
        $this->assertNotEmpty($secondCommitId);

        // Make sure it changed
        $this->assertNotEquals($firstCommitId, $secondCommitId);
    }

    /**
     * Make sure that after saving the isDirty flag is unset
     */
    public function testDirtyFlagUnsetOnSave()
    {
        $dm = $this->getDataMapper();
        if (!$dm) {
            return;
        }

        // Create first entity
        $customer = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::CONTACT, $this->account->getAccountId());
        $customer->setValue("name", "testNotDirty");
        $dm->save($customer, $this->user);

        // Queue for cleanup
        $this->testEntities[] = $customer;

        $this->assertFalse($customer->isDirty());
    }

    /**
     * Make sure that after saving the isDirty flag is unset
     */
    public function testDirtyFlagUnsetOnLoad()
    {
        $dm = $this->getDataMapper();
        if (!$dm) {
            return;
        }

        // Create first entity
        $customer = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::CONTACT, $this->account->getAccountId());
        $customer->setValue("name", "testNotDirty");
        $oid = $dm->save($customer, $this->user);

        // Queue for cleanup
        $this->testEntities[] = $customer;

        // Load into a new entity
        $ent = $dm->getEntityById($oid, $this->account->getAccountId());

        // Even though we just loaded all the data into the entity, it should not be marked as dirty
        $this->assertFalse($ent->isDirty());
    }

    /**
     * Test to make sure that saving an entity with recurrence works in the datamapper
     */
    public function testSaveAndLoadRecurrence()
    {
        $dm = $this->getDataMapper();

        // Create a simple recurrence pattern
        $recurrencePattern = new RecurrencePattern($this->account->getAccountId());
        $recurrencePattern->setRecurType(RecurrencePattern::RECUR_DAILY);
        $recurrencePattern->setDateStart(new \DateTime("2015-12-01"));
        $recurrencePattern->setDateEnd(new \DateTime("2015-12-02"));

        // Now save a task with this pattern and make sure it is given an id
        $task = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::TASK, $this->account->getAccountId());
        $task->setValue("name", "A test task");
        $task->setValue("start_date", date("Y-m-d", strtotime("2015-12-01")));
        $task->setValue("deadline", date("Y-m-d", strtotime("2015-12-01")));
        $task->setRecurrencePattern($recurrencePattern);
        $tid = $dm->save($task, $this->account->getAuthenticatedUser());
        $this->assertNotNull($recurrencePattern->getId());

        // Now close the task and reload it to make sure recurrence is still set
        $task2 = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->getEntityById($tid, $this->account->getAccountId());
        $this->assertNotNull($task2->getRecurrencePattern());

        // Cleanup
        $dm->delete($task2, $this->account->getAuthenticatedUser());
    }

    /**
     * Make sure that when we delete the parent object it deletes its recurrence pattern
     */
    public function testDeleteRecurrence()
    {
        $dm = $this->getDataMapper();

        // Create a simple recurrence pattern
        $recurrencePattern = new RecurrencePattern($this->account->getAccountId());
        $recurrencePattern->setRecurType(RecurrencePattern::RECUR_DAILY);
        $recurrencePattern->setDateStart(new \DateTime("2015-12-01"));
        $recurrencePattern->setDateEnd(new \DateTime("2015-12-02"));

        // Now save a task with this pattern
        $task = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::TASK, $this->account->getAccountId());
        $task->setValue("name", "A test task");
        $task->setValue("start_date", date("Y-m-d", strtotime("2015-12-01")));
        $task->setValue("deadline", date("Y-m-d", strtotime("2015-12-01")));
        $task->setRecurrencePattern($recurrencePattern);
        $tid = $dm->save($task, $this->user);

        $recurId = $recurrencePattern->getId();
        $this->assertNotEmpty($recurId);

        // Delete the object and make sure the pattern cannot be loaded
        $dm->delete($task, $this->account->getAuthenticatedUser());

        // Try to load recurId which should result in null
        $recurDm = $this->account->getServiceManager()->get(RecurrenceDataMapperFactory::class);
        $loadedPattern = $recurDm->load($recurId, $this->account->getAccountId());
        $this->assertNull($loadedPattern);
    }

    /**
     * Make sure that if we save an entity without fvals for fkey and object references
     * the datamapper will set them.
     */
    public function testUpdateForeignKeyNames()
    {
        $dm = $this->getDataMapper();
        if (!$dm) {
            // Do not run if we don't have a datamapper to work with
            $this->assertTrue(true);
            return;
        }

        // Create a few test groups
        $groupingsStat = $this->groupingDataMapper->getGroupingsByPath(ObjectTypes::CONTACT . "/status_id", $this->account->getAccountId());
        $statGrp = $groupingsStat->create("Unit Test Status");
        $groupingsStat->add($statGrp);
        $this->groupingDataMapper->saveGroupings($groupingsStat);

        $groupingsGroups = $this->groupingDataMapper->getGroupingsByPath(ObjectTypes::CONTACT . "/groups", $this->account->getAccountId());
        $groupsGrp = $groupingsGroups->create("Unit Test Group");
        $groupingsGroups->add($groupsGrp);
        $this->groupingDataMapper->saveGroupings($groupingsGroups);

        // Create an entity and initialize values
        $customer = $this->createCustomer();
        // fkey with no label (third param)
        $customer->setValue("status_id", $statGrp->getGroupId());
        // fkey_multi with no label (third param)
        $customer->addMultiValue("groups", $groupsGrp->getGroupId());
        // object with no label (third param)
        $customer->setValue("owner_id", $this->user->getEntityId());
        // Setting object_multi field with array values of null and empty string should not throw an error
        $customer->setValue("activity", [null, ""]);
        // Setting object field with array values of null should not throw an error
        $customer->setValue("primary_contact", null);

        // Save should call private updateForeignKeyNames in the DataMapperAbstract
        $cid = $dm->save($customer, $this->user);

        // Queue for cleanup
        $this->testEntities[] = $customer;

        // Load the entity from the datamapper
        $ent = $dm->getEntityById($cid, $this->account->getAccountId());

        // Make sure the fvals for references are updated
        $this->assertEquals($ent->getValueName("status_id", $statGrp->getGroupId()), $statGrp->getName(), var_export($ent->getValue('status_id_fkey'), true));
        $this->assertEquals($ent->getValueName("groups", $groupsGrp->getGroupId()), $groupsGrp->getName());
        $this->assertEquals($ent->getValueName("owner_id", $this->user->getEntityId()), $this->user->getName());

        // Cleanup groupings
        $groupingsStat->delete($statGrp->getGroupId());
        $this->groupingDataMapper->saveGroupings($groupingsStat);
        $groupingsGroups->delete($groupsGrp->getGroupId());
        $this->groupingDataMapper->saveGroupings($groupingsGroups);
    }

    /**
     * Test the public function for entityHasMoved
     */
    public function testCheckEntityHasMoved()
    {
        $dm = $this->getDataMapper();
        if (!$dm) {
            return;
        }

        // Create first entity
        $customer = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::CONTACT, $this->account->getAccountId());
        $customer->setValue("name", "testSetEntityMovedTo");
        $oid1 = $dm->save($customer, $this->user);

        // Queue for cleanup
        $this->testEntities[] = $customer;

        // Create second entity
        $customer2 = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::CONTACT, $this->account->getAccountId());
        $customer2->setValue("name", "testSetEntityMovedTo");
        $oid2 = $dm->save($customer2, $this->user);

        // Queue for cleanup
        $this->testEntities[] = $customer2;

        // Set moved to
        $def = $customer->getDefinition();
        $ret = $dm->setEntityMovedTo(
            $customer->getEntityId(),
            $customer2->getEntityId(),
            $this->account->getAccountId()
        );

        $movedTo = $dm->checkEntityHasMoved($customer->getDefinition(), $oid1, $this->account->getAccountId());

        // Now make sure the movedTo works
        $this->assertEquals($oid2, $movedTo);
    }

    /**
     * Make sure that veryfyUniqueName works
     */
    public function testVerifyUniqueName()
    {
        $dm = $this->getDataMapper();

        $uniqueName = uniqid();

        // Try saving an entity with an obviously unique name
        $customer = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::USER, $this->account->getAccountId());
        $isUnique = $dm->verifyUniqueName($customer, $uniqueName);
        $this->assertEquals(true, $isUnique);
    }

    /**
     * Make sure that veryfyUniqueName works
     */
    public function testVerifyUniqueNameFail()
    {
        $dm = $this->getDataMapper();

        $uniqueName = uniqid();

        // Try saving a dashboard entity with an obviously unique name
        $customer = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::USER, $this->account->getAccountId());
        $customer->setValue("uname", $uniqueName);
        $dm->save($customer, $this->user);


        // Queue for cleanup
        $this->testEntities[] = $customer;

        // Create a second entity and make sure we could not set the same uname
        $customer2 = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::USER, $this->account->getAccountId());
        $canReuse = $dm->verifyUniqueName($customer2, $uniqueName);
        $this->assertFalse($canReuse);
    }

    /**
     * Make sure that the datamapper is setting a unique name for entities
     */
    public function testSetUniqueName()
    {
        $dm = $this->getDataMapper();

        // Try saving an entity with an obviously unique name
        $customer = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::USER, $this->account->getAccountId());
        $customer->setValue("name", "test unique name");
        $dm->save($customer, $this->user);

        // Queue for cleanup
        $this->testEntities[] = $customer;

        $this->assertNotEmpty($customer->getValue("uname"));
    }

    /**
     * Test getting an entity by a unique name
     */
    public function testGetByUniqueName()
    {
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
        $dm = $this->getDataMapper();

        // Create site
        $site = $entityLoader->create(ObjectTypes::SITE, $this->account->getAccountId());
        $site->setValue("name", 'www.test.com');
        $dm->save($site, $this->user);
        $this->testEntities[] = $site; // for cleanup

        // Create root page for site
        $homePage = $entityLoader->create(ObjectTypes::PAGE, $this->account->getAccountId());
        $homePage->setValue("name", 'testgetbyunamehome'); // for uname
        $homePage->setValue("site_id", $site->getEntityId());
        $dm->save($homePage, $this->user);
        $this->testEntities[] = $homePage; // for cleanup

        // Create a subpage for the site
        $subPage = $entityLoader->create(ObjectTypes::PAGE, $this->account->getAccountId());
        $subPage->setValue("name", "testgetbyunamefile");  // for uname
        $subPage->setValue('parent_id', $homePage->getEntityId());
        $subPage->setValue("site_id", $site->getEntityId());
        $dm->save($subPage, $this->user);
        $this->testEntities[] = $subPage; // for cleanup

        // Uname should be namespaced with site_id:parent_id:nameOfEntity
        // since this is the settings of page: site_id:parent_id:name
        $namespacedUname = $site->getEntityId() . ':' . $homePage->getEntityId() . ':testgetbyunamefile';

        $this->assertEquals($namespacedUname, $subPage->getValue('uname'));

        $retrievedPage = $dm->getByUniqueName(
            ObjectTypes::PAGE,
            $namespacedUname,
            $this->account->getAccountId()
        );

        $this->assertEquals($subPage->getEntityId(), $retrievedPage->getEntityId());
    }

    /**
     * Make sure that we are able to save the object reference and update the referenced entity
     */
    public function testEntityObjectReference()
    {
        $dm = $this->getDataMapper();

        // Create an entity and initialize values
        $customerName = "Test Customer";
        $customer = $this->createCustomer();
        $customer->setValue("name", $customerName);
        $customer->setValue("owner_id", $this->user->getEntityId());
        $cid = $dm->save($customer, $this->user);

        $customerEntity = $dm->getEntityById($cid, $this->account->getAccountId());

        // Create reminder and set the customer as our object reference
        $customerReminder = "Customer Reminder";
        $reminder = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::REMINDER, $this->account->getAccountId());
        $reminder->setValue("name", $customerReminder);
        $reminder->setValue("obj_reference", $customer->getEntityId());
        $rid = $dm->save($reminder, $this->user);

        // Set the entities so it will be cleaned up properly
        $this->testEntities[] = $customer;
        $this->testEntities[] = $reminder;

        $reminderEntity = $dm->getEntityById($rid, $this->account->getAccountId());
        $this->assertEquals($customerEntity->getName(), $customerName);
        $this->assertEquals($reminderEntity->getName(), $customerReminder);
        $this->assertEquals($reminderEntity->getValue("obj_reference"), $customer->getEntityId());
        $this->assertEquals($reminderEntity->getValueName("obj_reference"), $customer->getName());
    }

    /**
     * Entities have some default fields, that have some important defaults
     *
     * @return void
     */
    public function testDefaultFieldsDefaults(): void
    {
        $dm = $this->getDataMapper();
        $customer = $this->createCustomer();
        $cid = $dm->save($customer, $this->user);
        $this->testEntities[] = $customer;

        // The seen_by field should default to the user who created the entity
        $this->assertEquals([$this->user->getEntityId()], $customer->getValue('seen_by'));
    }
}
