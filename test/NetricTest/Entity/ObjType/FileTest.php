<?php

/**
 * Test entity activity class
 */

namespace NetricTest\Entity\ObjType;

use Netric\Entity;
use PHPUnit\Framework\TestCase;
use Netric\FileSystem\FileStore\FileStoreFactory;
use NetricTest\Bootstrap;
use Netric\Entity\ObjType\UserEntity;
use Netric\EntityDefinition\EntityDefinitionLoader;
use Netric\Entity\EntityLoaderFactory;
use Netric\Entity\ObjType\FileEntity;
use Netric\Entity\DataMapper\EntityDataMapperFactory;
use Netric\Entity\DataMapper\EntityDataMapperInterface;
use Netric\EntityDefinition\ObjectTypes;

class FileTest extends TestCase
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
     * Test files
     *
     * @var Entity\ObjType\FileEntity[]
     */
    private $testFiles = [];

    /**
     * Entity DataMapper for creating, updating, and deleting files entities
     *
     * @var EntityDataMapperInterface
     */
    private $entityDataMapper = null;


    /**
     * Setup each test
     */
    protected function setUp(): void
    {
        $this->account = Bootstrap::getAccount();
        $this->entityDataMapper = $this->account->getServiceManager()->get(EntityDataMapperFactory::class);
        $this->user = $this->account->getUser(null, UserEntity::USER_SYSTEM);
    }

    /**
     * Clean-up and test files
     */
    protected function tearDown(): void
    {
        foreach ($this->testFiles as $file) {
            if ($file->getEntityId()) {
                $this->entityDataMapper->delete($file, $this->account->getAuthenticatedUser());
            }
        }
    }

    /**
     * Test dynamic factory of entity
     */
    public function testFactory()
    {
        $entity = $this->account->getServiceManager()->get(EntityLoaderFactory::class)->create(
            ObjectTypes::FILE,
            $this->account->getAccountId()
        );
        $this->assertInstanceOf(FileEntity::class, $entity);
    }

    /**
     * Verity that hard deleting a file purges from the file store
     */
    public function testOnDeleteHard()
    {
        $fileStore = $this->account->getServiceManager()->get(FileStoreFactory::class);

        // Create a new file & upload data
        $loader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
        $file = $loader->create(ObjectTypes::FILE, $this->account->getAccountId());
        $file->setValue("name", "test.txt");
        $this->entityDataMapper->save($file, $this->account->getAuthenticatedUser());
        $this->testFiles[] = $file;
        ;

        // Write data to the file
        $fileStore->writeFile($file, "my test data", $this->user);
        $this->assertTrue($fileStore->fileExists($file));

        // Open a copy to check the store later since the DataMapper will zero out $file
        $fileCopy = $loader->create(ObjectTypes::FILE, $this->account->getAccountId());
        $this->entityDataMapper->getEntityById($file->getEntityId(), $this->account->getAccountId());

        // Purge the file -- second param is a delete hard param
        $this->entityDataMapper->delete($file, $this->account->getAuthenticatedUser());

        // Test to make sure the data was deleted
        $this->assertFalse($fileStore->fileExists($fileCopy));
    }
}
