<?php

/**
 * Test saving files locally to disk
 */

namespace NetricTest\FileSystem\FileStore;

use Netric;
use Netric\Account\Account;
use PHPUnit\Framework\TestCase;
use Netric\FileSystem\FileStore;
use Netric\Entity\DataMapper\EntityDataMapperInterface;
use Netric\Entity\DataMapper\EntityDataMapperFactory;
use Netric\Entity\EntityLoaderFactory;
use Netric\EntityDefinition\ObjectTypes;

/**
 * @group integration
 */
abstract class AbstractFileStoreTests extends TestCase
{
    /**
     * Test files
     *
     * @var Netric\Entity\ObjType\FileEntity[]
     */
    private $testFiles = [];

    /**
     * Required for any FileStore implementation to constract and return a File Store
     *
     * @return FileStoreInterface
     */
    abstract protected function getFileStore();

    /**
     * Get the DataMapper for files
     *
     * @return EntityDataMapperInterface
     */
    private function getEntityDataMapper()
    {
        return $this->getAccount()->getServiceManager()->get(EntityDataMapperFactory::class);
    }

    private function getAccount(): Account
    {
        return \NetricTest\Bootstrap::getAccount();
        ;
    }

    private function createTestFile()
    {
        $loader = $this->getAccount()->getServiceManager()->get(EntityLoaderFactory::class);
        $dataMapper = $this->getEntityDataMapper();

        $file = $loader->create(ObjectTypes::FILE, $this->getAccount()->getAccountId());
        $file->setValue("name", "test.txt");
        $dataMapper->save($file, $this->getAccount()->getSystemUser());

        $this->testFiles[] = $file;

        return $file;
    }

    /**
     * Clean-up and test files
     */
    protected function tearDown(): void
    {
        $fileStore = $this->getFileStore();
        $dataMapper = $this->getEntityDataMapper();
        foreach ($this->testFiles as $file) {
            // Delete with this filestore since it may not
            if ($fileStore->fileExists($file)) {
                $fileStore->deleteFile($file);
            }

            $dataMapper->delete($file, \NetricTest\Bootstrap::getAccount()->getAuthenticatedUser());
        }
    }

    /**
     * Make sure we can write to a file
     */
    public function testWriteFile()
    {
        $fileStore = $this->getFileStore();
        $testFile = $this->createTestFile();

        $bytesWritten = $fileStore->writeFile(
            $testFile,
            "test contents",
            $this->getAccount()->getSystemUser()
        );
        $this->assertNotEquals(-1, $bytesWritten);
        $this->assertEquals($testFile->getValue("file_size"), $bytesWritten);
    }

    /**
     * Make sure we can write to a file
     */
    public function testWriteFileStream()
    {
        $fileStore = $this->getFileStore();
        $testFile = $this->createTestFile();

        // Create a temp stream
        $fileStream = tmpfile();
        fwrite($fileStream, "test contents");
        fseek($fileStream, 0);

        $bytesWritten = $fileStore->writeFile(
            $testFile,
            $fileStream,
            $this->getAccount()->getSystemUser()
        );
        $this->assertNotEquals(-1, $bytesWritten);
        $this->assertEquals($testFile->getValue("file_size"), $bytesWritten);

        fclose($fileStream);
    }

    /**
     * Make sure we can read the entire contents of a file
     */
    public function testReadFile()
    {
        $fileStore = $this->getFileStore();
        $testFile = $this->createTestFile();
        $content = "testReadFile Contents";

        $fileStore->writeFile(
            $testFile,
            $content,
            $this->getAccount()->getSystemUser()
        );

        $buf = $fileStore->readFile($testFile);
        $this->assertEquals($content, $buf);
    }

    /**
     * Test new file import
     */
    public function testUploadFile()
    {
        $fileStore = $this->getFileStore();
        $testFile = $this->createTestFile();
        $uploadFilePath = __DIR__ . "/fixtures/file-to-upload.txt";

        // Test importing a file into the FileSystem
        $ret = $fileStore->uploadFile($testFile, $uploadFilePath, $this->getAccount()->getAuthenticatedUser());
        $this->assertTrue($ret);

        // Try reading the file ato make sure data was imported
        $buf = $fileStore->readFile($testFile);

        // The contents of ./fixtures/file-to-upload.txt is: FileHasContent
        $this->assertEquals("FileHasContent", $buf);
    }

    /**
     * Make sure if I update a revision then it returns latest but keeps both versions
     */
    public function testUploadFileRevisions()
    {
        $fileStore = $this->getFileStore();
        $testFile = $this->createTestFile();
        $uploadFilePath = __DIR__ . "/fixtures/file-to-upload.txt";
        $uploadFile2Path = __DIR__ . "/fixtures/file-to-upload-2.txt";

        // Test importing a file into the FileSystem
        $ret = $fileStore->uploadFile($testFile, $uploadFilePath, $this->getAccount()->getAuthenticatedUser());
        $this->assertTrue($ret);

        /*
         * Try reading the file ato make sure data was imported
         * The contents of ./fixtures/file-to-upload.txt is: FileHasContent
         */
        $buf = $fileStore->readFile($testFile);
        $this->assertEquals("FileHasContent", $buf);

        // Re-import again with a new file
        $ret = $fileStore->uploadFile($testFile, $uploadFile2Path, $this->getAccount()->getAuthenticatedUser());
        $this->assertTrue($ret);

        /*
         * Try reading the file to make sure data was imported
         * The contents of ./fixtures/file-to-upload-2.txt is: FileHasContent2
         */
        $buf = $fileStore->readFile($testFile);
        $this->assertEquals("FileHasContent2", $buf);

        /*
         * Get all the revisions. There should be three:
         * 1 for original, 2 for first upload, 3 for second
         *
         * We will also load the second revision (index 1)
         * to make sure it is still the first file we uploaded
         * immediately after creating the file.
         */
        $dataMapper = $this->getEntityDataMapper();
        $files = $dataMapper->getRevisions($testFile->getEntityId(), $this->getAccount()->getAccountId());
        $keys = array_keys($files); // $fiels is an assoicative with key being revid
        $this->assertEquals(3, count($files));
        // The second revision (index 1) should be the first import
        $buf = $fileStore->readFile($files[$keys[1]]);
        // Make sure it read the first file
        $this->assertEquals("FileHasContent", $buf);
    }

    public function testDeleteFile()
    {
        $fileStore = $this->getFileStore();
        $testFile = $this->createTestFile();
        $uploadFilePath = __DIR__ . "/fixtures/file-to-upload.txt";
        $uploadFile2Path = __DIR__ . "/fixtures/file-to-upload-2.txt";

        // Upload two files, then make sure they are both deleted
        $fileStore->uploadFile($testFile, $uploadFilePath, $this->getAccount()->getAuthenticatedUser());
        $fileStore->uploadFile($testFile, $uploadFile2Path, $this->getAccount()->getAuthenticatedUser());

        // Delete the file - which will purge all revisions
        $this->assertTrue($fileStore->deleteFile($testFile));

        // Now loop through all revisions and make sure we purged them
        $dataMapper = $this->getEntityDataMapper();
        $files = $dataMapper->getRevisions($testFile->getEntityId(), $this->getAccount()->getAccountId());
        foreach ($files as $rev => $file) {
            $this->assertFalse($fileStore->fileExists($file));
        }
    }

    /**
     * Make sure the fileExists fuction works as expected
     */
    public function testFileExists()
    {
        $fileStore = $this->getFileStore();
        $testFile = $this->createTestFile();

        // Now write an actual file and make sure it exists
        $fileStore->writeFile(
            $testFile,
            "test contents",
            $this->getAccount()->getSystemUser()
        );
        $this->assertTrue($fileStore->fileExists($testFile));

        // Delete it and then make sure fileExists returns false
        $fileStore->deleteFile($testFile);

        $this->assertFalse($fileStore->fileExists($testFile));
    }
}
