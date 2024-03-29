<?php

/**
 * Test entity activity class
 */

namespace NetricTest\Entity\ObjType;

use Netric\Entity;
use PHPUnit\Framework\TestCase;
use Netric\Entity\EntityLoaderFactory;
use NetricTest\Bootstrap;
use Netric\Entity\ObjType\UserEntity;
use Netric\Entity\DataMapper\EntityDataMapperFactory;
use Netric\EntityQuery\Index\IndexFactory;
use Netric\EntityQuery\EntityQuery;
use Netric\EntityDefinition\ObjectTypes;
use Netric\EntityGroupings\GroupingLoaderFactory;
use Ramsey\Uuid\Uuid;

class UserTest extends TestCase
{
    /**
     * Tennant account
     *
     * @var \Netric\Account\Account
     */
    private $account = null;

    /**
     * Test user
     *
     * @var \Netric\Entity\ObjType\UserEntity
     */
    private $user = null;

    /**
     * Common constants used
     *
     * @cons string
     */
    const TEST_USER = "entity_objtype_test";
    const TEST_USER_PASS = "testpass";
    const TEST_EMAIL = "entity_objtype_test@netric.com";

    /**
     * User groups
     *
     * @var EntityGroupings
     */
    private $userGroups = null;

    /**
     * Setup each test
     */
    protected function setUp(): void
    {
        $this->account = Bootstrap::getAccount();

        // Setup entity datamapper for handling users
        $dm = $this->account->getServiceManager()->get(EntityDataMapperFactory::class);

        // Make sure old test user does not exist
        $query = new EntityQuery(ObjectTypes::USER, $this->account->getAccountId());
        $query->where('name')->equals(self::TEST_USER);
        $index = $this->account->getServiceManager()->get(IndexFactory::class);
        $res = $index->executeQuery($query);
        for ($i = 0; $i < $res->getTotalNum(); $i++) {
            $user = $res->getEntity($i);
            $dm->delete($user, $this->account->getAuthenticatedUser());
        }

        // Create a test user
        $loader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
        $user = $loader->create(ObjectTypes::USER, $this->account->getAccountId());
        $user->setValue("name", self::TEST_USER);
        $user->setValue("password", self::TEST_USER_PASS);
        $dm->save($user, $this->account->getSystemUser());
        $this->user = $user;

        $groupingLoader = $this->account->getServiceManager()->get(GroupingLoaderFactory::class);
        $this->userGroups = $groupingLoader->get(ObjectTypes::USER . '/groups', $this->account->getAccountId());
    }

    protected function tearDown(): void
    {
        if ($this->user) {
            $dm = $this->account->getServiceManager()->get(EntityDataMapperFactory::class);
            $dm->delete($this->user, $this->account->getAuthenticatedUser());
        }
    }

    /**
     * Test dynamic factory of entity
     */
    public function testFactory()
    {
        $entity = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::USER, $this->account->getAccountId());
        $this->assertInstanceOf(UserEntity::class, $entity);
    }

    public function testOnBeforeSave()
    {
        $oldPass = $this->user->getValue("password");
        $newPass = "newvalue";

        // onBeforeSave copies obj_reference to the 'associations' field
        $this->user->setValue("password", "newvalue");
        $this->user->onBeforeSave($this->account->getServiceManager(), $this->account->getSystemUser());

        // Make sure we have hashed and encoded the password
        $this->assertNotEquals($this->user->getValue("password"), $newPass);

        // And that the old password has also changed
        $this->assertNotEquals($this->user->getValue("password"), $oldPass);
    }

    public function testOnBeforeSaveNewUserPasswordSet()
    {
        $user = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::USER, $this->account->getAccountId());
        $user->setValue("name", self::TEST_USER);
        $user->setValue("password", self::TEST_USER_PASS);
        $user->onBeforeSave($this->account->getServiceManager(), $this->account->getSystemUser());

        // Make sure we have hashed and encoded the password
        $this->assertNotEquals($user->getValue("password"), self::TEST_USER_PASS);

        // Make sure we created a random salt
        $this->assertNotEmpty($user->getValue('password_salt'));
    }

    /**
     * Make sure a contact was created when the test user was created
     *
     * @return void
     */
    public function testOnBeforeSaveContactSet(): void
    {
        $user = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::USER, $this->account->getAccountId());
        $user->setValue("name", self::TEST_USER);
        $user->setValue("password", self::TEST_USER_PASS);
        $user->onBeforeSave($this->account->getServiceManager(), $this->account->getSystemUser());

        $this->assertNotEmpty($user->getValue('contact_id'));
    }

    public function testGenerateSaltForPasswords()
    {
        $user = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::USER, $this->account->getAccountId());
        $user->setValue("name", self::TEST_USER);
        $user->setValue("password", self::TEST_USER_PASS);
        $user->onBeforeSave($this->account->getServiceManager(), $this->account->getSystemUser());

        // Make sure we have hashed and encoded the password
        $this->assertNotEquals($user->getValue("password"), self::TEST_USER_PASS);
    }

    public function testOnAfterSaveAppicationEmailMapSet()
    {
        $user = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::USER, $this->account->getAccountId());
        $user->setValue("name", self::TEST_USER);
        $user->setValue("email", self::TEST_EMAIL);
        $user->setValue("password", self::TEST_USER_PASS);
        $user->onAfterSave($this->account->getServiceManager(), $this->account->getSystemUser());

        // Make sure the application can get the username from the email now
        $app = $this->account->getApplication();
        $accounts = $app->getAccountsByEmail(self::TEST_EMAIL);
        $this->assertEquals(1, count($accounts));
        $this->assertEquals($accounts[0]['username'], self::TEST_USER);
    }

    public function testOnAfterSaveAppicationEmailMapSetChanged()
    {
        $app = $this->account->getApplication();

        $user = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(ObjectTypes::USER, $this->account->getAccountId());
        $user->setValue("name", self::TEST_USER);
        $user->setValue("email", self::TEST_EMAIL);
        $user->setValue("password", self::TEST_USER_PASS);
        $user->onAfterSave($this->account->getServiceManager(), $this->account->getSystemUser());

        // Change the username and make sure the old username was deleted
        $user->setValue("name", self::TEST_USER . "-changed");
        $user->onAfterSave($this->account->getServiceManager(), $this->account->getSystemUser());

        // Make sure the application can get the username from the email now
        $accounts = $app->getAccountsByEmail(self::TEST_EMAIL);
        $this->assertEquals(1, count($accounts));
        $this->assertEquals($accounts[0]['username'], self::TEST_USER . "-changed");

        // Reset
        $user->setValue("name", self::TEST_USER);
        $user->onAfterSave($this->account->getServiceManager(), $this->account->getSystemUser());

        // Change the email and make sure the old username was deleted
        $user->setValue("email", self::TEST_EMAIL . "-changed");
        $user->onAfterSave($this->account->getServiceManager(), $this->account->getSystemUser());

        // Make sure the application can get the username from the email now
        $accounts = $app->getAccountsByEmail(self::TEST_EMAIL);
        $this->assertEquals(0, count($accounts));
    }

    public function testGetGroups()
    {
        $adminGroup = $this->userGroups->getByName(UserEntity::GROUP_ADMINISTRATORS);
        $this->user->addMultiValue("groups", $adminGroup->getGroupId());

        $groups = $this->user->getGroups();

        // Make sure administrators was added
        $this->assertTrue(in_array($adminGroup->getGroupId(), $groups));

        // Make sure default users was also added
        $userGroup = $this->userGroups->getByName(UserEntity::GROUP_USERS);
        $this->assertTrue(in_array($userGroup->getGroupId(), $groups));
    }

    // Test before adding any groups that the default USERS groups was added
    public function testGetGroupsDefault()
    {
        $usersGroup = $this->userGroups->getByName(UserEntity::GROUP_USERS);
        $everyoneGroup = $this->userGroups->getByName(UserEntity::GROUP_EVERYONE);

        $groups = $this->user->getGroups();
        $this->assertTrue(in_array($usersGroup->getGroupId(), $groups));
        $this->assertTrue(in_array($everyoneGroup->getGroupId(), $groups));
    }

    /**
     * We override getOwnerId to always be self::id
     */
    public function testGetOwnerGuid()
    {
        $sm = $this->account->getServiceManager();
        $user = $sm->get(EntityLoaderFactory::class)->create(ObjectTypes::USER, $this->account->getAccountId());

        $userGuid = Uuid::uuid4()->toString();
        $user->setValue('entity_id', $userGuid);
        $user->setValue('owner_id', Uuid::uuid4()->toString());

        // Normally the entity would return the owner_id, but users always return themselves
        $this->assertEquals($userGuid, $user->getOwnerId());
    }

    /**
     * Make sure we export only select fields if the user has no permissions
     *
     * @return void
     */
    public function testToArrayWithNoPermissions()
    {
        $sm = $this->account->getServiceManager();
        $user = $sm->get(EntityLoaderFactory::class)->create(ObjectTypes::USER, $this->account->getAccountId());
        $user->setValue("name", 'testuser');
        $user->setValue("full_name", 'Test User');

        $data = $user->toArrayWithNoPermissions();
        // The user entity also exports full_name, make sure it is set
        $this->assertEquals($user->getValue('full_name'), $data['full_name']);

        // All other fields are tested in the ../EntityTest.php tests
        // but we'll test obj_type here just ot make sure the
        // parent::toArrayWithNoPermissions() was called
        $this->assertEquals(ObjectTypes::USER, $data['obj_type']);
    }
}
