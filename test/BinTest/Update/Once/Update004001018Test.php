<?php
/**
 * Make sure the bin/scripts/update/once/004/001/018.php script works
 */
namespace BinTest\Update\Once;

use Netric\Entity\EntityLoaderFactory;
use Netric\EntityDefinition\EntityDefinitionLoaderFactory;
use Netric\Db\Relational\RelationalDbFactory;
use Netric\Authentication\AuthenticationServiceFactory;
use Netric\Entity\DataMapper\DataMapperFactory as EntityDataMapperFactory;
use Netric\Console\BinScript;
use PHPUnit\Framework\TestCase;
use Netric\EntityDefinition\ObjectTypes;

class Update004001018Test extends TestCase
{
    /**
     * Handle to account
     *
     * @var \Netric\Account\Account
     */
    private $account = null;

    /**
     * Path to the script to test
     *
     * @var string
     */
    private $scriptPath = null;

    /**
     * Test entities that should be cleaned up on tearDown
     *
     * @var EntityInterface[]
     */
    private $testEntities = [];

    /**
     * Test old entities that should be cleaned up on tearDown
     *
     * @var EntityInterface[]
     */
    private $testOldEntities = [];

    /**
     * Setup each test
     */
    protected function setUp(): void
{
        $this->account = \NetricTest\Bootstrap::getAccount();
        $this->scriptPath = __DIR__ . "/../../../../bin/scripts/update/once/004/001/018.php";
    }

    /**
     * Cleanup after a test runs
     */
    protected function tearDown(): void
{
        // Cleanup any test entities
        $loader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
        foreach ($this->testEntities as $entity) {
            $loader->delete($entity, true);
        }
    }

    /**
     * Make sure the file exists
     *
     * This is more a test of the test to make sure we set the path right, but why
     * not just use unit tests for our tests? :)
     */
    public function testExists()
    {
        $this->assertTrue(file_exists($this->scriptPath), $this->scriptPath . " not found!");
    }

    /**
     * At a basic level, make sure we can run without throwing any exceptions
     */
    public function testRun()
    {
        $serviceManager = $this->account->getServiceManager();
        $entityLoader = $serviceManager->get(EntityLoaderFactory::class);
        $entityDefinitionLoader = $serviceManager->get(EntityDefinitionLoaderFactory::class);
        $entityDataMapper = $serviceManager->get(EntityDataMapperFactory::class);
        $db = $serviceManager->get(RelationalDbFactory::class);

        $objType = ObjectTypes::TASK;
        $insertData = [
            'name' => "UnitTestTask"
        ];
        $taskId = $db->insert('project_tasks', $insertData);

        $binScript = new BinScript($this->account->getApplication(), $this->account);
        $this->assertTrue($binScript->run($this->scriptPath));

        // Test the task if it was moved to the new object table
        $def = $entityDefinitionLoader->get($objType);
        $movedEntityId = $entityDataMapper->checkEntityHasMoved($def, $taskId);
        $taskEntity = $entityLoader->get($objType, $movedEntityId);
        $this->testEntities[] = $taskEntity;

        $this->assertEquals($taskEntity->getName(), "UnitTestTask");
        $this->assertEquals($taskEntity->getId(), $movedEntityId);
    }
}