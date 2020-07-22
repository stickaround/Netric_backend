<?php

/**
 * Test the WorkFlow class
 */

namespace NetricTest\WorkFlow;

use Netric\WorkFlow\WorkFlowFactory;
use Netric\WorkFlow\Action\ActionFactory;
use Netric\EntityQuery\Where;
use PHPUnit\Framework\TestCase;
use Netric\EntityDefinition\ObjectTypes;
use Ramsey\Uuid\Uuid;
use NetricTest\Bootstrap;

class WorkFlowTest extends TestCase
{
    /**
     * Create some test IDs
     */
    const TEST_ACTION_ID = '86e9ecbf-fe4d-4a2f-b84f-b355173992c4';
    const TEST_WORKFLOW_ID = '8cd88c04-055f-4373-bd7d-7a61dc9b3b6e';

    /**
     * Reference to account running for unit tests
     *
     * @var \Netric\Account\Account
     */
    private $account = null;

    /**
     * Action factory for testing
     *
     * @var ActionFactory
     */
    protected $actionFactory = null;

    /**
     * ServiceLocator for injecting dependencies
     * 
     * @var AccountServiceManagerInterface
     */
    private $sl = null;

    protected function setUp(): void
    {
        $this->account = Bootstrap::getAccount();
        $this->sl = $this->account->getServiceManager();
        $this->actionFactory = new ActionFactory($this->sl);
    }

    /**
     * Make sure we can convert a workflow to and from an array
     */
    public function testFromAndToArray()
    {
        $workFlowData = array(
            "guid" => self::TEST_WORKFLOW_ID,
            "name" => "Test",
            "obj_type" => ObjectTypes::TASK,
            "notes" => "Details Here",
            "active" => true,
            "on_create" => true,
            "on_update" => true,
            "on_delete" => true,
            "singleton" => false,
            "allow_manual" => false,
            "only_on_conditions_unmet" => true,
            "conditions" => array(
                array(
                    "blogic" => Where::COMBINED_BY_AND,
                    "field_name" => "fiest_field",
                    "operator" => Where::OPERATOR_EQUAL_TO,
                    "value" => "someval",
                ),
                array(
                    "blogic" => Where::COMBINED_BY_OR,
                    "field_name" => "second_field",
                    "operator" => Where::OPERATOR_NOT_EQUAL_TO,
                    "value" => "someval",
                ),
            ),
            "actions" => array(
                array(
                    "id" => self::TEST_WORKFLOW_ID,
                    "name" => "my action",
                    "type" => "test",
                    "workflow_id" => self::TEST_ACTION_ID,
                    "parent_action_id" => 1,
                    "actions" => array(
                        array(
                            "id" => 567,
                            "name" => "my child action",
                            "type" => "test",
                            "workflow_id" => self::TEST_ACTION_ID,
                            "parent_action_id" => self::TEST_WORKFLOW_ID,
                        )
                    )
                ),
            ),
        );

        $workFlow = $this->sl->get(WorkFlowFactory::class);
        $workFlow->fromArray($workFlowData);

        // Now get the array back and make sure it matches the original
        $retrievedData = $workFlow->toArray();

        /*
         * Test that whatever is in $retrievedData matches what we set in $workFlowData.
         * We can't just do assertEquals because defaults may have been set in addition
         * to what is in $workFlowData such as 'revision' which will cause it to fail.
         */
        foreach ($workFlowData as $key => $value) {
            if (is_array($value)) {
                // Test expected nested array values
                foreach ($value as $subValueKey => $subValue) {
                    foreach ($subValue as $entryKey => $entryValue) {
                        if (is_array($entryValue)) {
                            // We can only go so deep, just check to make sure there same number of elements
                            $this->assertEquals(
                                count($entryValue),
                                count($retrievedData[$key][$subValueKey][$entryKey])
                            );
                        } else {
                            $this->assertEquals(
                                $entryValue,
                                $retrievedData[$key][$subValueKey][$entryKey]
                            );
                        }
                    }
                }
            } else {
                $this->assertEquals($value, $retrievedData[$key]);
            }
        }
    }

    public function testRemoveAction()
    {
        $workFlow = $this->sl->get(WorkFlowFactory::class);

        // Create a test action
        $action = $this->actionFactory->create("test");
        $action->setId(100);
        $workFlow->addAction($action);

        // Test removing the action when it's the same object
        $this->assertTrue($workFlow->removeAction($action));
        $this->assertEquals(0, count($workFlow->getActions()));
        $this->assertEquals(1, count($workFlow->getRemovedActions()));

        // Add again which should clear it from the 'to be removed' queue
        $workFlow->addAction($action);
        $this->assertEquals(1, count($workFlow->getActions()));
        $this->assertEquals(0, count($workFlow->getRemovedActions()));

        // Now try removing with a new object that has the same id
        $actionClone = $this->actionFactory->create("test");
        $actionClone->setId(100);
        $this->assertTrue($workFlow->removeAction($actionClone));
        $this->assertEquals(0, count($workFlow->getActions()));
        $this->assertEquals(1, count($workFlow->getRemovedActions()));
    }

    public function testGetRemovedActions()
    {
        $workFlow = $this->sl->get(WorkFlowFactory::class);

        // Create a test action
        $action = $this->actionFactory->create("test");
        $action->setId(100);
        $workFlow->addAction($action);

        // Now delete it
        $workFlow->removeAction($action);
        $removedActions = $workFlow->getRemovedActions();
        $this->assertEquals($action->getWorkFlowActionId(), $removedActions[0]->getWorkFlowActionId());
    }

    public function testAddAction()
    {
        $workFlow = $this->sl->get(WorkFlowFactory::class);

        // Create a test action
        $action = $this->actionFactory->create("test");
        $action->setId(100);
        $workFlow->addAction($action);
        $this->assertEquals(1, count($workFlow->getActions()));

        // Try adding the same action again which should result only in one
        $workFlow->addAction($action);
        $this->assertEquals(1, count($workFlow->getActions()));

        // Remove it, then add again and make sure removed is empty
        $workFlow->removeAction($action);
        $workFlow->addAction($action);

        $this->assertEquals(1, count($workFlow->getActions()));
        $this->assertEquals(0, count($workFlow->getRemovedActions()));
    }

    public function testGetActions()
    {
        $workFlow = $this->sl->get(WorkFlowFactory::class);

        // Create a test action
        $action = $this->actionFactory->create("test");
        $action->setId(100);
        $workFlow->addAction($action);

        // Make sure the action is in the queue of actions
        $actions = $workFlow->getActions();
        $this->assertEquals($action->getId(), $actions[0]->getId());
    }

    public function testFromArrayJsonEncodedConditions()
    {
        $uuid = Uuid::uuid4()->toString();
        $conditionOwner = new Where("owner_id");
        $conditionOwner->equals($uuid);

        $conditionFlag = new Where("flag");
        $conditionFlag->equals(true);

        $workFlow = $this->sl->get(WorkFlowFactory::class);

        $workFlowData["conditions"] = json_encode([$conditionOwner->toArray(), $conditionFlag->toArray()]);
        $workFlow->fromArray($workFlowData);

        $conditions = $workFlow->getConditions();
        $this->assertEquals(count($conditions), 2);
        $this->assertEquals($conditions[0]->fieldName, "owner_id");
        $this->assertEquals($conditions[0]->value, $uuid);
        $this->assertEquals($conditions[1]->fieldName, "flag");
        $this->assertEquals($conditions[1]->value, true);
    }
}