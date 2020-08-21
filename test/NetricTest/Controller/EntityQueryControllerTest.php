<?php

/**
 * Test the entity query controller
 */

namespace NetricTest\Controller;

use Netric\Controller\EntityQueryController;
use Netric\Entity\EntityLoaderFactory;
use PHPUnit\Framework\TestCase;
use NetricTest\Bootstrap;
use Netric\EntityDefinition\ObjectTypes;

/**
 * @group integration
 */
class EntityQueryControllerTest extends TestCase
{
    /**
     * Account used for testing
     *
     * @var \Netric\Account\Account
     */
    protected $account = null;

    /**
     * Controller instance used for testing
     *
     * @var EntityController
     */
    protected $controller = null;

    /**
     * Test entities that should be cleaned up on tearDown
     *
     * @var EntityInterface[]
     */
    private $testEntities = [];

    protected function setUp(): void
    {
        $this->account = Bootstrap::getAccount();

        // Get the service manager of the current user
        $this->serviceManager = $this->account->getServiceManager();

        // Create the controller
        $this->controller = new EntityQueryController($this->account->getApplication(), $this->account);
        $this->controller->testMode = true;
    }

    /**
     * Cleanup after a test runs
     */
    protected function tearDown(): void
    {
        // Cleanup any test entities
        $loader = $this->serviceManager->get(EntityLoaderFactory::class);
        foreach ($this->testEntities as $entity) {
            $loader->delete($entity, $this->account->getAuthenticatedUser());
        }
    }

    public function testPostExecuteAction()
    {
        $entityLoader = $this->serviceManager->get(EntityLoaderFactory::class);
        $taskEntity = $entityLoader->create(ObjectTypes::TASK, $this->account->getAccountId());
        $taskEntity->setValue("name", "UnitTestTask");
        $entityLoader->save($taskEntity, $this->account->getAuthenticatedUser());
        $this->testEntities[] = $taskEntity;

        // Set params in the request
        $data = ['obj_type' => ObjectTypes::TASK];
        $req = $this->controller->getRequest();
        $req->setBody(json_encode($data));
        $req->setParam('content-type', 'application/json');

        $ret = $this->controller->postExecuteAction();

        $this->assertGreaterThan(0, $ret['total_num']);
        $this->assertGreaterThan(0, $ret['num']);
        $this->assertEquals("task", $ret["entities"][0]["obj_type"]);
        $this->assertEquals($ret["entities"][0]["currentuser_permissions"], ['view' => true, 'edit' => true, 'delete' => true]);

        // Now let's try to query the entities using a user that has no permissions to access the entities
        $userEntity = $entityLoader->create(ObjectTypes::USER, $this->account->getAccountId());
        $userEntity->setValue("name", "Test User");
        $entityLoader->save($userEntity, $this->account->getAuthenticatedUser());
        $this->testEntities[] = $userEntity;

        $account = Bootstrap::getAccount();
        $account->setCurrentUser($userEntity);

        // Create the controller
        $controller = new EntityQueryController($this->account->getApplication(), $account);
        $controller->testMode = true;

        // Set params in the request
        $data = ['obj_type' => ObjectTypes::TASK];
        $req = $controller->getRequest();
        $req->setBody(json_encode($data));
        $req->setParam('content-type', 'application/json');

        $ret = $controller->postExecuteAction();

        // This should only retrieve 3 fields from the task since the user does not have
        // permissions to see the full entity: entity_id, name, currentuser_permissions.
        $this->assertGreaterThanOrEqual(count($ret["entities"][0]), 3);
        $this->assertNotEmpty($ret["entities"][0]["entity_id"]);
        $this->assertNotEmpty($ret["entities"][0]["name"]);
        $this->assertEquals($ret["entities"][0]["currentuser_permissions"], [
            'view' => false, 'edit' => false, 'delete' => false
        ]);
    }
}
