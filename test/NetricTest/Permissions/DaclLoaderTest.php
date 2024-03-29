<?php

namespace NetricTest\Permissions;

use PHPUnit\Framework\TestCase;
use Netric\Permissions;
use Netric\Entity\ObjType\UserEntity;
use Netric\Entity\EntityInterface;
use Netric\Account\Account;
use Netric\Permissions\DaclLoader;
use Netric\Permissions\Dacl;
use Netric\Entity\EntityLoaderFactory;
use Netric\Permissions\DaclLoaderFactory;
use Netric\EntityDefinition\EntityDefinitionLoaderFactory;
use Netric\EntityDefinition\ObjectTypes;
use NetricTest\Bootstrap;

/**
 * @group integration
 */
class DaclLoaderTest extends TestCase
{
    /**
     * Active test account
     *
     * @var Account
     */
    private $account = null;

    /**
     * Loader for testing
     *
     * @var DaclLoader
     */
    private $daclLoader = null;

    /**
     * The user that owns the email account
     *
     * @var UserEntity
     */
    private $user = null;

    /**
     * Store the dacl for files since we will modify it below
     *
     * @var Dacl
     */
    private $origFileDacl = null;

    /**
     * Any test entities created
     *
     * @var EntityInterface[]
     */
    private $testEntities = [];

    protected function setUp(): void
    {
        $this->account = Bootstrap::getAccount();
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);

        // Create a temporary user
        $this->user = $entityLoader->create(ObjectTypes::USER, $this->account->getAccountId());
        $this->user->setValue("name", "utest-email-receiver-" . rand());
        $this->user->addMultiValue("groups", UserEntity::GROUP_USERS);
        $entityLoader->save($this->user, $this->account->getSystemUser());
        $this->testEntities[] = $this->user;

        // Let's store the current file DACL since we will modify it, and we want to restore it on shutdonw
        $definitionLoader = $this->account->getServiceManager()->get(EntityDefinitionLoaderFactory::class);
        $fileDef = $definitionLoader->get(ObjectTypes::FILE, $this->account->getAccountId());
        $this->origFileDacl = $fileDef->getDacl();

        // Reset DACL for files
        $fileDef->setDacl(null);

        $this->daclLoader = $this->account->getServiceManager()->get(DaclLoaderFactory::class);
    }

    protected function tearDown(): void
    {
        $serviceLocator = $this->account->getServiceManager();

        // Delete any test entities
        $entityLoader = $serviceLocator->get(EntityLoaderFactory::class);
        foreach ($this->testEntities as $entity) {
            $entityLoader->delete($entity, $this->account->getAuthenticatedUser());
        }

        // Restore original permissions to the file definition
        $definitionLoader = $this->account->getServiceManager()->get(EntityDefinitionLoaderFactory::class);
        $fileDef = $definitionLoader->get(ObjectTypes::FILE, $this->account->getAccountId());
        $fileDef->setDacl($this->origFileDacl);
    }

    /**
     * Test getting a dacl for a specific entity
     */
    public function testGetForEntity()
    {
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);

        // New file
        $file = $entityLoader->create(ObjectTypes::FILE, $this->account->getAccountId());
        $file->setValue("name", "myFiletest.txt");
        $daclData = [
            "entries" => [
                [
                    "name" => Dacl::PERM_VIEW,
                    "users" => [$this->user->getEntityId()]
                ],
            ],
        ];
        $file->setValue("dacl", json_encode($daclData));
        $entityLoader->save($file, $this->user);
        $this->testEntities[] = $file;

        $dacl = $this->daclLoader->getForEntity($file, $this->user);
        $this->assertNotNull($dacl);

        // Test if the user added worked
        $this->assertTrue($dacl->isAllowed($this->user, Dacl::PERM_VIEW));
    }

    /**
     * Test getting a dacl for a specific entity with an invalid dacl data, it should return the definition's dacl
     */
    public function testGetForEntityWithInvalidDaclData()
    {
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);

        // New file
        $file = $entityLoader->create(ObjectTypes::FILE, $this->account->getAccountId());
        $file->setValue("name", "myFiletest.txt");
        $daclData = [
            "entries" => [
                [
                    "name" => Dacl::PERM_VIEW,
                    "users" => [-1]
                ],
            ],
        ];
        $file->setValue("dacl", json_encode($daclData));
        $entityLoader->save($file, $this->user);
        $this->testEntities[] = $file;

        $dacl = $this->daclLoader->getForEntity($file, $this->user);
        $this->assertNotNull($dacl);

        // Dacl should not have an invalid dacl data
        $this->assertTrue(sizeof($dacl->getUsers()) === 0);
    }

    /**
     * Test getting a dacl from an inherited parent entity like a file from a folder
     */
    public function testGetForEntityParent()
    {
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);

        // New folder which is the parent of a file
        $folder = $entityLoader->create(ObjectTypes::FOLDER, $this->account->getAccountId());
        $folder->setValue("name", "MyFolder");
        $folderDacl = new Dacl();
        $folderDacl->allowUser($this->user->getEntityId(), Dacl::PERM_VIEW);
        $folder->setValue("dacl", json_encode($folderDacl->toArray()));
        $entityLoader->save($folder, $this->user);
        $this->testEntities[] = $folder;

        // New file that is a child of the parent
        $file = $entityLoader->create(ObjectTypes::FILE, $this->account->getAccountId());
        $file->setValue("folder_id", $folder->getEntityId());
        $file->setValue("name", "myFiletest.txt");
        $entityLoader->save($file, $this->user);
        $this->testEntities[] = $file;

        // The file does not have an explicit DACL, so it should load from the folder
        $dacl = $this->daclLoader->getForEntity($file, $this->user);
        $this->assertEquals([$this->user->getEntityId()], $dacl->getUsers());
    }

    /**
     * Test getting a dacl from an inherited field
     */
    public function testGetForEntitInherit()
    {
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);

        // Create a new room, which makes a custom DACL with the contents of members field
        $chatRoom = $entityLoader->create(ObjectTypes::CHAT_ROOM, $this->account->getAccountId());
        $chatRoom->setValue("subject", "MyFolder");
        $chatRoom->addMultiValue("members", $this->user->getEntityId(), $this->user->getName());
        $entityLoader->save($chatRoom, $this->user);
        $this->testEntities[] = $chatRoom;

        // Now put a message in the field
        $chatMessage = $entityLoader->create(ObjectTypes::CHAT_MESSAGE, $this->account->getAccountId());
        $chatMessage->setValue("chat_room", $chatRoom->getEntityId());
        $chatMessage->setValue("body", "Test Message");
        $entityLoader->save($chatMessage, $this->user);
        $this->testEntities[] = $chatMessage;

        // The file does not have an explicit DACL, so it should load from the folder
        $dacl = $this->daclLoader->getForEntity($chatMessage, $this->user);
        $this->assertEquals([$this->user->getEntityId()], $dacl->getUsers());
    }

    /**
     * Test falling back to get a DACL from an entity defition - all should have a DACL
     */
    public function testGetForEntity_Definition()
    {
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);

        // New file
        $file = $entityLoader->create(ObjectTypes::FILE, $this->account->getAccountId());
        $file->setValue("name", "myFiletest.txt");
        $entityLoader->save($file, $this->user);
        $this->testEntities[] = $file;

        // Set the DACL for the entity type
        $def = $file->getDefinition();
        $defDacl = new Dacl();
        $defDacl->allowUser($this->user->getEntityId(), Dacl::PERM_FULL);
        $def->setDacl($defDacl);

        $dacl = $this->daclLoader->getForEntity($file, $this->user);
        $this->assertNotNull($dacl);

        // Test if the DACL we got back came from the definition (only one that gives the user access)
        $this->assertTrue($dacl->isAllowed($this->user, Dacl::PERM_VIEW));
    }

    /**
     * Test getting a default DACL if there is no Dacl for the object type
     */
    public function testGetForEntity_Default()
    {
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);

        // New file with no DACL
        $file = $entityLoader->create(ObjectTypes::FILE, $this->account->getAccountId());
        $file->setValue("name", "myFiletest.txt");
        $file->setValue("owner_id", $this->user->getEntityId());
        $entityLoader->save($file, $this->user);
        $this->testEntities[] = $file;

        $dacl = $this->daclLoader->getForEntity($file, $this->user);
        $this->assertNotNull($dacl);

        // It will pull the default which only gives access to admins and creator owner
        $this->assertFalse($dacl->isAllowed($this->user));

        // Try creator owner
        $this->assertTrue($dacl->isAllowed($this->user, Dacl::PERM_DEFAULT, $file));
    }
}
