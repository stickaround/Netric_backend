<?php

/**
 * Test the entity activity log
 */

namespace NetricTest\Entity;

use Netric;
use Netric\Authentication\AuthenticationServiceFactory;
use Netric\Entity\ActivityLog;
use Netric\Entity\EntityLoader;
use Netric\Entity\ObjType\ActivityEntity;
use PHPUnit\Framework\TestCase;
use Netric\Entity\ActivityLogFactory;
use Netric\Entity\EntityEvents;
use NetricTest\Bootstrap;
use Netric\Entity\EntityLoaderFactory;
use Netric\EntityDefinition\ObjectTypes;

class ActivityLogTest extends TestCase
{
    /**
     * Tenant account
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
     * Activity log
     *
     * @var ActivityLog
     */
    private $activityLog = null;

    /**
     * Entity loader for creating and saving entities
     *
     * @var EntityLoader
     */
    private $entityLoader = null;

    /**
     * Setup each test
     */
    protected function setUp(): void
    {
        $this->account = Bootstrap::getAccount();
        $this->user = $this->account->getAuthenticatedUser();
        $this->activityLog = $this->account->getServiceManager()->get(ActivityLogFactory::class);
        $this->entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
    }

    /**
     * Make sure we can log a basic activity
     */
    public function testLog()
    {
        // Create a test customer
        $customerEntity = $this->entityLoader->create(ObjectTypes::CONTACT, $this->account->getAccountId());
        $customerEntity->setValue("name", "Test Customer Log");
        $this->entityLoader->save($customerEntity, $this->account->getAuthenticatedUser());

        // Log the activity
        $act = $this->activityLog->log($this->user, EntityEvents::EVENT_CREATE, $customerEntity);
        $openedAct = $this->entityLoader->getEntityById($act->getEntityId(), $this->account->getAccountId());

        // Test activity
        $this->assertNotNull($openedAct);
        $this->assertNotEmpty($openedAct->getValueName("subject"));

        // Cleanup
        $this->entityLoader->delete($customerEntity, $this->account->getAuthenticatedUser());
    }
}
