<?php

/**
 * Test entity/object class
 */

namespace NetricTest\EntitySync\Collection;

use Netric\EntitySync;
use Netric\EntitySync\Collection;
use PHPUnit\Framework\TestCase;
use Netric\EntitySync\Collection\EntityCollection;
use Netric\EntityQuery\Index\IndexFactory;
use Netric\Entity\EntityLoaderFactory;
use Netric\EntityDefinition\ObjectTypes;
use Netric\Entity\DataMapper\EntityDataMapperFactory;

/**
 * @group integration
 */
class EntityCollectionTest extends AbstractCollectionTests
{
    /**
     * New objects created
     *
     * @var \Netric\Entity[]
     */
    private $newCreated = [];

    /**
     * @return Collection\EntityCollection
     */
    protected function getCollection()
    {
        $index = $this->account->getServiceManager()->get(IndexFactory::class);
        $collection = new EntityCollection(
            $this->esDataMapper,
            $this->commitManager,
            $index,
            $this->account->getAccountId()
        );
        $collection->setObjType(ObjectTypes::CONTACT);
        return $collection;
    }

    protected function createLocal()
    {
        // Create new customer with the passed data
        $newEnt = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::CONTACT, $this->account->getAccountId());
        $newEnt->setValue("name", "EntityEyncTests");
        $this->account->getServiceManager()->get(EntityDataMapperFactory::class)->save(
            $newEnt,
            $this->account->getSystemUser()
        );
        $this->newCreated[] = $newEnt;
        return ["id" => $newEnt->getEntityId(), "revision" => $newEnt->getValue("commit_id")];
    }

    protected function changeLocal($id)
    {
        foreach ($this->newCreated as $createdEnt) {
            if ($createdEnt->getEntityId() == $id) {
                // Record object change
                $createdEnt->setValue("name", "EntityEyncTests_2");
                $this->account->getServiceManager()->get(EntityDataMapperFactory::class)->save(
                    $createdEnt,
                    $this->account->getSystemUser()
                );
            }
        }
    }

    protected function deleteLocal($id = null)
    {
        foreach ($this->newCreated as $createdEnt) {
            if ($id == $createdEnt->getEntityId() || $id == null) {
                $this->account->getServiceManager()->get(EntityDataMapperFactory::class)->delete($createdEnt, $this->account->getAuthenticatedUser());
            }
        }
    }

    /**
     * Test exporting imported
     */
    public function testGetExportChangedImported()
    {
        $pid = "AntObjectSync_CollectionTest::testGetChangedObjects";

        // Create customer just in case there are none already in the database
        $customer = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::CONTACT, $this->account->getAccountId());
        $customer->setValue("name", "EntityEyncTests");
        $this->account->getServiceManager()->get(EntityDataMapperFactory::class)->save(
            $customer,
            $this->account->getSystemUser()
        );

        // Create and save partner with one collection watching customers
        $partner = new EntitySync\Partner($this->esDataMapper);
        $partner->setRemotePartnerId($pid);
        $partner->setOwnerId($this->user->getEntityId());
        $collection = $this->getCollection();
        $collection->setObjType(ObjectTypes::CONTACT);
        $this->esDataMapper->savePartner($partner);

        // Initial pull should start with all objects
        $stats = $collection->getExportChanged($this->account->getAccountId());
        $this->assertTrue(count($stats) >= 1);
        $collection->fastForwardToHead();

        // Should be no changes now
        $stats = $collection->getExportChanged($this->account->getAccountId());
        $this->assertEquals(0, count($stats));

        // Record object change
        $customer->setValue("name", "EntityEyncTests_2");
        $this->account->getServiceManager()->get(EntityDataMapperFactory::class)->save(
            $customer,
            $this->account->getSystemUser()
        );

        // Make sure the one change is now returned
        $stats = $collection->getExportChanged($this->account->getAccountId());
        $this->assertTrue(count($stats) >= 1);
        $this->assertEquals($stats[0]['id'], $customer->getEntityId());

        // Cleanup
        $this->esDataMapper->deletePartner($partner);
        $this->account->getServiceManager()->get(EntityDataMapperFactory::class)->delete($customer, $this->account->getAuthenticatedUser());
    }

    /**
     * Make sure we can detect when an entity has been deleted
     */
    public function testGetExportChanged_Deleted()
    {
        $pid = "AntObjectSync_CollectionTest::testGetChangedObjects";

        // Create customer just in case there are none already in the database
        $customer = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::CONTACT, $this->account->getAccountId());
        $customer->setValue("name", "EntityEyncTests");
        $this->account->getServiceManager()->get(EntityDataMapperFactory::class)->save(
            $customer,
            $this->account->getSystemUser()
        );
        $customerId = $customer->getEntityId();

        // Create and save partner with one collection watching customers
        $partner = new EntitySync\Partner($this->esDataMapper);
        $partner->setRemotePartnerId($pid);
        $partner->setOwnerId($this->user->getEntityId());
        $collection = $this->getCollection();
        $collection->setObjType(ObjectTypes::CONTACT);
        $partner->addCollection($collection);
        $this->esDataMapper->savePartner($partner);

        // Get all exported which will cause the customer to be logged
        while (count($stats = $collection->getExportChanged($this->account->getAccountId()))) {
        }

        // Fast-forward past the created customer
        $collection->fastForwardToHead();
        $stats = $collection->getExportChanged($this->account->getAccountId());
        $this->assertEquals(0, count($stats));

        // Soft delete the customer
        $this->account->getServiceManager()->get(EntityDataMapperFactory::class)->archive($customer, $this->account->getAuthenticatedUser());

        // Make sure the one change is now returned for the deleted item
        $stats = $collection->getExportChanged($this->account->getAccountId());
        $this->assertEquals(1, count($stats));
        $this->assertEquals($customerId, $stats[0]['id']);
        $this->assertEquals("delete", $stats[0]['action']);

        // Make sure a next call does not return the stale item again
        $stats = $collection->getExportChanged($this->account->getAccountId());
        $this->assertEquals(0, count($stats));

        // Cleanup
        $this->esDataMapper->deletePartner($partner);
        $this->account->getServiceManager()->get(EntityDataMapperFactory::class)->delete($customer, $this->account->getAuthenticatedUser());
    }
}
