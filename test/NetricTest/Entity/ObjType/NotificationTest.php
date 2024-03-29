<?php

/**
 * Test notification
 */

namespace NetricTest\Entity\ObjType;

use Netric\Entity\EntityInterface;
use Netric\Entity\EntityLoader;
use Netric\Entity\ObjType\UserEntity;
use PHPUnit\Framework\TestCase;
use Netric\EntityQuery\EntityQuery;
use NetricTest\Bootstrap;
use Netric\Entity\EntityLoaderFactory;
use Netric\Entity\ObjType\NotificationEntity;
use Netric\EntityQuery\Index\IndexFactory;
use Netric\EntityDefinition\ObjectTypes;

class NotificationTest extends TestCase
{
    /**
     * Tennant account
     *
     * @var \Netric\Account\Account
     */
    private $account = null;

    /**
     * Administrative user
     *
     * @var \Netric\User
     */
    private $user = null;

    /**
     * Test user to notify
     *
     * @var User
     */
    private $testUser = null;

    /**
     * EntityLoader
     *
     * @var EntityLoader
     */
    private $entityLoader = null;

    /**
     * List of test entities to cleanup
     *
     * @var EntityInterface[]
     */
    private $testEntities = [];

    /**
     * Setup each test
     */
    protected function setUp(): void
    {
        $this->account = Bootstrap::getAccount();
        $this->user = $this->account->getUser(null, UserEntity::USER_SYSTEM);
        $this->entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);


        // Make sure test user does not exist from previous failed query
        $index = $this->account->getServiceManager()->get(IndexFactory::class);
        $query = new EntityQuery(ObjectTypes::USER, $this->account->getAccountId());
        $query->where("name")->equals("notificationtest");
        $result = $index->executeQuery($query);
        for ($i = 0; $i < $result->getNum(); $i++) {
            $this->entityLoader->delete($result->getEntity($i), $this->account->getAuthenticatedUser());
        }

        // Create a test user to assign a task and notification to
        $this->testUser = $this->entityLoader->create(ObjectTypes::USER, $this->account->getAccountId());
        $this->testUser->setValue("name", "notificationtest");
        $this->testUser->setValue("email", "test@netric.com");
        $this->entityLoader->save($this->testUser, $this->account->getSystemUser());
        $this->testEntities[] = $this->testUser;
    }

    /**
     * Cleanup after each test
     */
    protected function tearDown(): void
    {
        // Make sure any test entities created are deleted
        foreach ($this->testEntities as $entity) {
            // Second param is a 'hard' delete which actually purges the data
            $this->entityLoader->delete($entity, $this->account->getAuthenticatedUser());
        }
    }

    /**
     * Test dynamic factory of entity
     */
    public function testFactory()
    {
        $entity = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::NOTIFICATION, $this->account->getAccountId());
        $this->assertInstanceOf(NotificationEntity::class, $entity);
    }
}
