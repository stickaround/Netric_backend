<?php

namespace NetricTest\Permissions;

use PHPUnit\Framework\TestCase;
use Netric\Permissions;
use Netric\Entity\ObjType\UserEntity;
use Netric\Entity\EntityInterface;
use Netric\Permissions\Dacl;
use NetricTest\Bootstrap;
use Netric\Entity\EntityLoaderFactory;
use Netric\EntityDefinition\ObjectTypes;
use Netric\EntityGroupings\Group;
use Netric\EntityGroupings\GroupingLoaderFactory;

use Ramsey\Uuid\Uuid;

/**
 * @group integration
 */
class DaclTest extends TestCase
{
    /**
     * Active test account
     *
     * @var Account
     */
    private $account = null;

    /**
     * The user that owns the email account
     *
     * @var UserEntity
     */
    private $user = null;

    /**
     * Any test entities created
     *
     * @var EntityInterface[]
     */
    private $testEntities = [];

    /**
     * Users group
     *
     * @var Group
     */
    private $userGroup = null;

    /**
     * Setup a DACL
     */
    protected function setUp(): void
    {
        $this->account = Bootstrap::getAccount();
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
        $groupingsLoader = $this->account->getServiceManager()->get(GroupingLoaderFactory::class);
        $userGroups = $groupingsLoader->get(ObjectTypes::USER . '/groups', $this->account->getAccountId());
        $this->userGroup = $userGroups->getByName(UserEntity::GROUP_USERS);

        // Create a temporary user
        $this->user = $entityLoader->create(ObjectTypes::USER, $this->account->getAccountId());
        $this->user->setValue("name", "utest-email-receiver-" . rand());
        $this->user->addMultiValue("groups", $this->userGroup->getGroupId());
        $entityLoader->save($this->user, $this->account->getSystemUser());
        $this->testEntities[] = $this->user;
    }

    protected function tearDown(): void
    {
        $serviceLocator = $this->account->getServiceManager();

        // Delete any test entities
        $entityLoader = $serviceLocator->get(EntityLoaderFactory::class);
        foreach ($this->testEntities as $entity) {
            $entityLoader->delete($entity, $this->account->getAuthenticatedUser());
        }
    }

    public function testAllowUser()
    {
        $dacl = new Dacl();

        // First pass will fail since users was not given access
        $this->assertFalse($dacl->isAllowed($this->user));

        // Add USERS group and then test again
        $dacl->allowUser($this->user->getEntityId());

        $this->assertTrue($dacl->isAllowed($this->user));
    }

    public function testAllowGroup()
    {
        $dacl = new Dacl();

        // First pass will fail since users was not given access
        $this->assertFalse($dacl->isAllowed($this->user));

        // Add USERS group and then test again
        $dacl->allowGroup($this->userGroup->getGroupId());

        $this->assertTrue($dacl->isAllowed($this->user));
    }

    public function testDenyUser()
    {
        $dacl = new Dacl();

        // Add user which should cause it to pass
        $dacl->allowUser($this->user->getEntityId());
        $this->assertTrue($dacl->isAllowed($this->user));

        // Remove the user which should cause it to fail
        $dacl->denyUser($this->user->getEntityId());
        $this->assertFalse($dacl->isAllowed($this->user));
    }

    public function testDenyGroup()
    {
        $dacl = new Dacl();

        // Add user which should cause it to pass
        $dacl->allowGroup($this->userGroup->getGroupId());
        $this->assertTrue($dacl->isAllowed($this->user));

        // Remove the user which should cause it to fail
        $dacl->denyGroup($this->userGroup->getGroupId());
        $this->assertFalse($dacl->isAllowed($this->user));
    }

    public function testFromArray()
    {
        $data = [
            "entries" => [
                [
                    "name" => Dacl::PERM_VIEW,
                    "groups" => [$this->userGroup->getGroupId()],
                    "users" => [$this->user->getEntityId()]
                ],
            ],
        ];

        $dacl = new Dacl();
        $dacl->fromArray($data);

        // Make sure it was loaded
        $this->assertTrue($dacl->isAllowed($this->user, Dacl::PERM_VIEW));

        // Make a new user and add them to the group to test
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
        $user2 = $entityLoader->create(ObjectTypes::USER, $this->account->getAccountId());
        $user2->setValue("name", "utest-dacl-" . rand());
        $user2->addMultiValue("groups", $this->userGroup->getGroupId());
        $entityLoader->save($user2, $this->account->getSystemUser());
        $this->testEntities[] = $user2;

        // Make make sure groups were populated
        $this->assertTrue($dacl->isAllowed($user2, Dacl::PERM_VIEW));
    }

    public function testToArray()
    {
        $dacl = new Dacl();
        $dacl->allowGroup($this->userGroup->getGroupId());
        $dacl->allowUser($this->user->getEntityId());

        $exported = $dacl->toArray();
        $this->assertEquals([$this->userGroup->getGroupId()], $exported['entries']['View']['groups']);
    }

    public function testGetUsers()
    {
        $dacl = new Dacl();
        $dacl->allowUser($this->user->getEntityId());

        $users = $dacl->getUsers();
        $this->assertEquals(1, count($users));
        $this->assertEquals([$this->user->getEntityId()], $users);
    }

    public function testGetGroups()
    {
        $dacl = new Dacl();
        $dacl->allowGroup($this->userGroup->getGroupId());

        $groups = $dacl->getGroups();
        $this->assertEquals(1, count($groups));
        $this->assertEquals([$this->userGroup->getGroupId()], $groups);
    }

    public function testGroupIsAllowed()
    {
        $dacl = new Dacl();
        $dacl->allowGroup($this->userGroup->getGroupId());

        // Make sure anonymous access is not allowed if only authenticated users were given access
        $this->assertFalse($dacl->groupIsAllowed(UserEntity::GROUP_EVERYONE, Dacl::PERM_VIEW));

        // Make sure users group is allowed
        $this->assertTrue($dacl->groupIsAllowed($this->userGroup->getGroupId(), Dacl::PERM_VIEW));

        // Now give everyone view only access and test
        $dacl->allowGroup(UserEntity::GROUP_EVERYONE, Dacl::PERM_VIEW);
        $this->assertTrue($dacl->groupIsAllowed(UserEntity::GROUP_EVERYONE, Dacl::PERM_VIEW));

        // But not edit
        $this->assertFalse($dacl->groupIsAllowed(UserEntity::GROUP_EVERYONE, Dacl::PERM_EDIT));
    }

    public function testIsAllowedOnEntity()
    {
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
        $user = $entityLoader->create(ObjectTypes::USER, $this->account->getAccountId());
        $user->setValue("entity_id", Uuid::uuid4()->toString());

        $userOwner = $entityLoader->create(ObjectTypes::USER, $this->account->getAccountId());
        $userOwner->setValue("entity_id", Uuid::uuid4()->toString());

        $task = $entityLoader->create(ObjectTypes::TASK, $this->account->getAccountId());
        $task->setValue('owner_id', $userOwner->getEntityId());

        $dacl = new Dacl();
        $this->assertTrue($dacl->isAllowed($userOwner, null, $task));

        // This should be false since the $userNotAssigned is not assigned in the task
        $userNotAssigned = $entityLoader->create(ObjectTypes::USER, $this->account->getAccountId());
        $userNotAssigned->setValue("entity_id", Uuid::uuid4()->toString());
        $this->assertFalse($dacl->isAllowed($userNotAssigned, null, $task));
    }

    /**
     * Run tests that will check for user permissions
     */
    public function testGetUserPermissions()
    {
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
        $user = $entityLoader->create(ObjectTypes::USER, $this->account->getAccountId());
        $user->setValue("entity_id", Uuid::uuid4()->toString());

        $userOwner = $entityLoader->create(ObjectTypes::USER, $this->account->getAccountId());
        $userOwner->setValue("entity_id", Uuid::uuid4()->toString());

        $task = $entityLoader->create(ObjectTypes::TASK, $this->account->getAccountId());
        $task->setValue('owner_id', $userOwner->getEntityId());

        $dacl = new Dacl();
        $ownerPermissions = $dacl->getUserPermissions($userOwner, $task);
        $this->assertEquals($ownerPermissions, ['view' => true, 'edit' => true, 'delete' => true]);

        // This should be false since the $userNotAssigned is not assigned in the task
        $userPermissions = $dacl->getUserPermissions($user, $task);
        $this->assertEquals($userPermissions, ['view' => false, 'edit' => false, 'delete' => false]);

        // Now let's try allowing user to view this task
        $dacl->allowUser($user->getEntityId(), DACL::PERM_VIEW);
        $updatedPermissions = $dacl->getUserPermissions($user);
        $this->assertEquals($updatedPermissions, ['view' => true, 'edit' => false, 'delete' => false]);

        // Give the user full access to the task entity
        $dacl->allowUser($user->getEntityId(), DACL::PERM_FULL);
        $fullPermission = $dacl->getUserPermissions($user);
        $this->assertEquals($fullPermission, ['view' => true, 'edit' => true, 'delete' => true]);
    }

    /**
     * Run tests that will verify the validity of dacl data
     */
    public function testVerifyDaclData()
    {
        $dacl = new Dacl();

        // Set a valid group id 
        $dacl->allowGroup($this->userGroup->getGroupId(), Dacl::PERM_VIEW);
        $this->assertEquals($dacl->verifyDaclData(), true);

        // Now set an invalid group id 
        $dacl->allowGroup(-1, Dacl::PERM_VIEW);
        $this->assertEquals($dacl->verifyDaclData(), false);
    }
}
