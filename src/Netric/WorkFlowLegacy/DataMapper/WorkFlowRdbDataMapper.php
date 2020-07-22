<?php

namespace Netric\WorkFlowLegacy\DataMapper;

use Netric\Entity\EntityLoader;
use Netric\EntityQuery\Index\IndexInterface;
use Netric\EntityQuery;
use Netric\EntityDefinition\ObjectTypes;
use Netric\WorkFlowLegacy\WorkFlowLegacy;
use Netric\WorkFlowLegacy\WorkFlowLegacyInstance;
use Netric\WorkFlowLegacy\Action\ActionFactory;
use Netric\WorkFlowLegacy\Action\ActionInterface;
use Netric\Db\Relational\RelationalDbInterface;
use DateTime;
use Ramsey\Uuid\Uuid;

/**
 * Relational Database datamapper for CRUD operations on a WorkFlowLegacy object
 */
class WorkFlowLegacyRdbDataMapper extends AbstractDataMapper implements DataMapperInterface
{
    /**
     * Handle to database
     *
     * @var RelationalDbInterface
     */
    private $database = null;

    /**
     * Action factory needed to construct new WorkFlowLegacy objects
     *
     * @var ActionFactory|null
     */
    private $actionFactory = null;

    /**
     * Entity loader for loading up the entity being acted on
     *
     * @var EntityLoader
     */
    private $entityLoader = null;

    /**
     * Index used for querying entities - mostly actions
     *
     * @var IndexInterface
     */
    private $entityIndex = null;

    /**
     * Construct the WorkFlowLegacy DataMapper
     *
     * @param RelationalDbInterface $database Handles to database actions
     * @param EntityLoader $entityLoader Entity loader for loading up the entities
     * @param IndexInterface $entityIndex Index used for querying entities - mostly actions
     * @param ActionFactory $actionFactory Factory to create new actions
     */
    public function __construct(
        RelationalDbInterface $database,
        EntityLoader $entityLoader,
        IndexInterface $entityIndex,
        ActionFactory $actionFactory
    ) {
        $this->database = $database;
        $this->entityLoader = $entityLoader;
        $this->entityIndex = $entityIndex;
        $this->actionFactory = $actionFactory;
    }

    /**
     * Save a new or existing WorkFlowLegacy
     *
     * @param WorkFlowLegacy $workFlow The workflow to save
     * @return int|null The unique id if success, null on failure (call getLastError for details)
     * @throws \RuntimeException on critical unexpected error
     */
    public function save(WorkFlowLegacy $workFlow)
    {
        $data = $workFlow->toArray();

        $workflowEntity = null;
        if ($workFlow->getWorkFlowLegacyId()) {
            $workflowEntity = $this->entityLoader->getByGuid($workFlow->getWorkFlowLegacyId());
        } else {
            $workflowEntity = $this->entityLoader->create(ObjectTypes::WORKFLOW);
        }

        // Set entity values
        $workflowEntity->setValue("name", $data['name']);
        $workflowEntity->setValue("notes", $data['notes']);
        $workflowEntity->setValue("object_type", $data['obj_type']);
        $workflowEntity->setValue("f_active", $data['active']);
        $workflowEntity->setValue("f_on_create", $data['on_create']);
        $workflowEntity->setValue("f_on_update", $data['on_update']);
        $workflowEntity->setValue("f_on_delete", $data['on_delete']);
        $workflowEntity->setValue("f_on_daily", $data['on_daily']);
        $workflowEntity->setValue("f_singleton", $data['singleton']);
        $workflowEntity->setValue("f_allow_manual", $data['allow_manual']);
        $workflowEntity->setValue("f_condition_unmet", $data['only_on_conditions_unmet']);
        $workflowEntity->setValue("ts_lastrun", $data['last_run']);

        // Set conditions
        if (count($data['conditions'])) {
            $workflowEntity->setValue(
                "conditions",
                json_encode($data['conditions'])
            );
        }

        // Save the entity
        $workflowId = $this->entityLoader->save($workflowEntity);

        // Set the id
        $workFlow->setWorkflowId($workflowEntity->getEntityId());

        // Save actions
        $this->saveActions($workFlow->getActions(), $workFlow->getRemovedActions(), $workFlow->getWorkFlowLegacyId());

        return $workflowId;
    }

    /**
     * Delete an existing WorkFlowLegacy
     *
     * @param WorkFlowLegacy $workFlow The workflow to delete
     * @return true on success, false on failure with detauls in getLastError
     */
    public function delete(WorkFlowLegacy $workFlow)
    {
        if (!$workFlow->getWorkFlowLegacyId()) {
            throw new \InvalidArgumentException("Cannot delete a workflow that has not been saved");
        }

        // Query the workflow action entities so we can delete them
        $query = new EntityQuery(ObjectTypes::WORKFLOW_ACTION);
        $query->andWhere("workflow_id")->equals($workFlow->getWorkFlowLegacyId());
        $result = $this->entityIndex->executeQuery($query);

        if (!$result) {
            throw new \RuntimeException("Could not get actions: " . $this->entityIndex->getLastError());
        }

        $num = $result->getNum();

        // Loop thru the result and delete the workflow action entities
        for ($i = 0; $i < $num; $i++) {
            $workflowActionEntity = $result->getEntity($i);
            $this->entityLoader->delete($workflowActionEntity, true);
        }

        // Delete the workflow
        $workflowEntity = $this->entityLoader->getByGuid($workFlow->getWorkFlowLegacyId());
        if ($workflowEntity) {
            $this->entityLoader->delete($workflowEntity, true);
            return true;
        }

        return false;
    }

    /**
     * Open a new workflow by id
     *
     * @param int $workflowId The unique id of the workflow to load
     * @return WorkFlowLegacy|null Returns null if $id does not exist
     */
    public function getById($workflowId)
    {
        if (!is_numeric($workflowId)) {
            return null;
        }

        $entityWorkflow = $this->entityLoader->getByGuid($workflowId);

        if ($entityWorkflow) {
            return $this->constructWorkFlowLegacyFromRow($entityWorkflow->toArray());
        }

        return null;
    }

    /**
     * Get a list of WorkFlowLegacys as an array
     *
     * @param string $objType If set only get for a specific object type
     * @param bool $onlyActive Only return active workflows, otherwise return all
     * @param string $filterEvent If set, only get workflows listening for a specific event
     * @return WorkFlowLegacy[] An array of WorkFlowLegacy objects or just an empty array if none found
     * @throws \RuntimeException If the query fails
     */
    public function getWorkFlowLegacys($objType = null, $onlyActive = true, $filterEvent = null)
    {
        // Query all actions
        $query = new EntityQuery(ObjectTypes::WORKFLOW);

        if ($onlyActive) {
            $query->andWhere("f_active")->equals(true);
        }

        if ($objType) {
            $query->andWhere("object_type")->equals($objType);
        }

        // Add event filter
        switch ($filterEvent) {
            case 'create':
                $query->andWhere("f_on_create")->equals(true);
                break;
            case 'update':
                $query->andWhere("f_on_update")->equals(true);
                break;
            case 'delete':
                $query->andWhere("f_on_delete")->equals(true);
                break;
                /* Below are for future expansions to periodic workflows
            case 'yearly':
                $sql .= " AND f_on_yearly is TRUE";
                $sql .= " AND (
                            ts_lastrun<='" . date("Y-m-d H:i:s T", strtotime("-1 year")) . "'
                            OR ts_lastrun IS NULL
                          )";
                break;
            case 'monthly':
                $sql .= " AND f_on_monthly is TRUE";
                $sql .= " AND (
                            ts_lastrun<='" . date("Y-m-d H:i:s T", strtotime("-1 month")) . "'
                            OR ts_lastrun IS NULL
                          )";
                break;
            case 'weekly':
                $sql .= " AND f_on_weekly is TRUE";
                $sql .= " AND (
                            ts_lastrun<='" . date("Y-m-d H:i:s T", strtotime("-1 week")) . "'
                            OR ts_lastrun IS NULL
                          )";
                break;
            case 'hourly':
                $sql .= " AND f_on_hourly is TRUE";
                $sql .= " AND (
                            ts_lastrun<='" . date("Y-m-d H:i:s T", strtotime("-1 hour")) . "'
                            OR ts_lastrun IS NULL
                          )";
                break;
            */
            case 'daily':
                $query->andWhere("f_on_daily")->equals(true);
                $query->andWhere("ts_lastrun")->isLessOrEqualTo(date("Y-m-d H:i:s T", strtotime("-1 day")));
                $query->orWhere("ts_lastrun")->equals(null);
                break;
            default:
                // Do nothing
                break;
        }

        $result = $this->entityIndex->executeQuery($query);
        if (!$result) {
            throw new \RuntimeException("Could not get actions: " . $this->entityIndex->getLastError());
        }

        $workFlows = [];
        $num = $result->getNum();
        for ($i = 0; $i < $num; $i++) {
            $entityWorkflow = $result->getEntity($i);

            $workFlows[] = $this->constructWorkFlowLegacyFromRow($entityWorkflow->toArray());
        }

        return $workFlows;
    }

    /**
     * Construct a WorkFlowLegacy object from a $row from the database
     *
     * @param array $row Associative array of a row of the 'workfows' table
     * @return WorkFlowLegacy
     */
    private function constructWorkFlowLegacyFromRow($row)
    {
        // Construct to workflow to fill
        $workFlow = new WorkFlowLegacy($this->actionFactory);

        // Create data array to import
        $importData = array(
            "id" => $row['entity_id'],
            "guid" => $row['entity_id'],
            "name" => $row['name'],
            "obj_type" => $row['object_type'],
            "notes" => $row['notes'],
            "revision" => $row['revision'],
            "active" => $row['f_active'],
            "on_create" => $row['f_on_create'],
            "on_update" => $row['f_on_update'],
            "on_delete" => $row['f_on_delete'],
            "on_daily" => $row['f_on_daily'],
            "singleton" => $row['f_singleton'],
            "allow_manual" => $row['f_allow_manual'],
            "last_run" => $row['ts_lastrun'],
            "only_on_conditions_unmet" => $row['f_condition_unmet'],
            "conditions" => ($row['conditions']) ? json_decode($row['conditions'], true) : null,
            "actions" => $this->getActionsArray($row['entity_id']),
        );

        // Set the data from the row
        $workFlow->fromArray($importData);

        return $workFlow;
    }

    /**
     * Save an instance of a workflow
     *
     * @param WorkFlowLegacyInstance $workFlowInstance Instance to save
     * @return int id The unique id of the instance run
     * @throws \InvalidArgumentException if the instance has not been initialized property
     * @throws \RunTimeException If the query fails to save the instance
     */
    public function saveWorkFlowLegacyInstance(WorkFlowLegacyInstance $workFlowInstance)
    {
        // Make sure the instance is valid
        if (!$workFlowInstance->isValid()) {
            throw new \InvalidArgumentException("Workflow instance has not been set");
        }

        // Setup column values to set
        $workflowData = array(
            "workflow_id" => $workFlowInstance->getWorkFlowLegacyId(),
            "object_type_id" => $workFlowInstance->getObjTypeId(),
            "object_type" => $workFlowInstance->getObjType(),
            "object_uid" => $workFlowInstance->getEntityId(),
            "ts_started" => $workFlowInstance->getTimeStarted()->format("Y-m-d H:i:s T"),
            "f_completed" => (($workFlowInstance->isCompleted()) ? 't' : 'f'),
        );

        $workFlowInstanceId = $workFlowInstance->getWorkFlowLegacyInstanceId();

        if ($workFlowInstanceId) {
            $entity = $this->entityLoader->getByGuid($workFlowInstanceId);
        } else {
            $entity = $this->entityLoader->create(ObjectTypes::WORKFLOW_INSTANCE);
        }

        $entity->fromArray($workflowData);
        $workFlowInstanceId = $this->entityLoader->save($entity);
        $workFlowInstance->setId($workFlowInstanceId);

        return $workFlowInstanceId;
    }

    /**
     * Get a WorkFlowLegacyInstance by id
     *
     * @param int $workFlowInstanceId The unique id of the workflow instance running
     * @return WorkFlowLegacyInstance|null
     */
    public function getWorkFlowLegacyInstanceById($workFlowInstanceId)
    {
        $workflowInstanceEntity = $this->entityLoader->getByGuid($workFlowInstanceId);

        if ($workflowInstanceEntity) {
            $objectType = $workflowInstanceEntity->getValue("object_type");
            $objectUid = $workflowInstanceEntity->getValue("object_uid");
            $workflowId = $workflowInstanceEntity->getValue("workflow_id");
            $completedFlag = $workflowInstanceEntity->getValue("f_completed");

            $entity = $this->entityLoader->getByGuid($objectUid);

            // Entity was deleted
            if (!$entity) {
                return null;
            }

            $workFlowInstance = new WorkFlowLegacyInstance($workflowId, $entity, $workFlowInstanceId);
            $workFlowInstance->setTimeStarted(new \DateTime($row['ts_started']));
            $workFlowInstance->setCompleted(($completedFlag === 1) ? true : false);
            return $workFlowInstance;
        }

        // Assume failure
        return null;
    }

    /**
     * Delete a workflow instance id
     *
     * This is only for admin really because an instance will almost always be set to completed
     * but never deleted since we want to maintain a record of the instance run.
     *
     * @param int $workFlowInstanceId The workflow instance id that we are going to delete
     * @throws \InvalidArgumentException if anything but a workFlowInstanceId is passed
     */
    public function deleteWorkFlowLegacyInstance($workFlowInstanceId)
    {
        if (!is_numeric($workFlowInstanceId)) {
            throw new \InvalidArgumentException("Only a valid WorkFlowLegacyInstance id must be passed");
        }

        $this->database->delete(
            'workflow_instances',
            ['id' => $workFlowInstanceId]
        );
    }

    /**
     * Get conditions array for a workflow
     *
     * @param int $workflowGuid Global unique id of the workflow to get actions for
     * @param int $parentActionGuid Get child actions for a parent action
     * @param array $circularCheck Log of previously added actions to avoid circular references
     * @return array
     */
    private function getActionsArray($workflowGuid, $parentActionGuid = null, $circularCheck = [])
    {
        if (!Uuid::isValid($workflowGuid) && !Uuid::isValid($parentActionGuid)) {
            throw new \InvalidArgumentException("A valid workflow id or parent action id must be passed");
        }

        $actionsArray = [];

        // Query all actions
        $query = new EntityQuery(ObjectTypes::WORKFLOW_ACTION);
        if ($parentActionGuid) {
            $query->where("parent_action_id")->equals($parentActionGuid);
        } else {
            $query->where("parent_action_id")->equals("");
            $query->andWhere("workflow_id")->equals($workflowGuid);
        }

        $result = $this->entityIndex->executeQuery($query);
        if (!$result) {
            throw new \RuntimeException("Could not get actions: " . $this->entityIndex->getLastError());
        }

        $num = $result->getNum();
        for ($i = 0; $i < $num; $i++) {
            $workflowActionEntity = $result->getEntity($i);

            /*
             * Actions can be children of other actions.
             * Check to make sure there are no circular relationships where a child
             * lists a parent as it's own child - that would be very bad!
             */
            if (in_array($workflowActionEntity->getEntityId(), $circularCheck)) {
                throw new \RunTimeException($workflowActionEntity->getEntityId() . " is a curcular dependency because it was already added");
            } else {
                $circularCheck[] = $workflowActionEntity->getEntityId();
            }

            // If type is not defined then throw an exception since it is required
            if (!$workflowActionEntity->getValue("type_name")) {
                throw new \RuntimeException("Action " . $workflowActionEntity->getEntityId() . " does not have a type_name set");
            }

            $actionArray = [
                "guid" => $workflowActionEntity->getEntityId(),
                "name" => $workflowActionEntity->getValue("name"),
                "workflow_id" => $workflowActionEntity->getValue("workflow_id"),
                "type" => $workflowActionEntity->getValue("type_name"),
                "parent_action_id" => $workflowActionEntity->getValue("parent_action_id"),
                "actions" => $this->getActionsArray($workflowActionEntity->getValue("workflow_id"), $workflowActionEntity->getEntityId(), $circularCheck),
            ];

            if ($workflowActionEntity->getValue("data")) {
                $actionArray['params'] = json_decode($workflowActionEntity->getValue("data"), true);
            }

            $actionsArray[] = $actionArray;
        }

        return $actionsArray;
    }

    /**
     * Save actions for a workflow or a parent action
     *
     * @param ActionInterface[] $actionsToAdd
     * @param ActionInterface[] $actionsToRemove
     * @param int $workflowId
     * @param int $parentActionId
     */
    private function saveActions(array $actionsToAdd, array $actionsToRemove, $workflowGuid, $parentActionGuid = null)
    {
        if (!Uuid::isValid($workflowGuid) && !Uuid::isValid($parentActionGuid)) {
            throw new \InvalidArgumentException("Must pass either workflowGuid or parentActionGuid as params");
        }

        // First purge any actions queued to be deleted
        foreach ($actionsToRemove as $action) {
            $actionEntity = $this->entityLoader->getByGuid($action->getWorkFlowLegacyActionId());
            if (!$this->entityLoader->delete($actionEntity, true)) {
                throw new \RuntimeException("Could not delete action");
            }
        }

        foreach ($actionsToAdd as $action) {
            $this->saveAction($action, $workflowGuid, $parentActionGuid);
        }
    }

    /**
     * Save an individual action
     *
     * @param ActionInterface $actionToSave
     * @param int $workflowId
     * @param int $parentActionId
     * @return bool true on success, false on failure
     */
    private function saveAction(ActionInterface $actionToSave, $workflowGuid, $parentActionGuid = null)
    {
        $actionData = $actionToSave->toArray();

        if (!isset($actionData['type']) || !$actionData['type']) {
            throw new \InvalidArgumentException("Type is required but not set in: " . var_export($actionData, true));
        }

        $actionEntity = $this->entityLoader->create(ObjectTypes::WORKFLOW_ACTION);
        $actionEntity->setValue("type", 0); // for legacy code - can eventually delete when /lib/Workflow is deleted
        $actionEntity->setValue("type_name", $actionData['type']);
        $actionEntity->setValue("name", $actionData['name']);
        $actionEntity->setValue("workflow_id", $workflowGuid);
        $actionEntity->setValue("parent_action_id", $parentActionGuid);
        $actionEntity->setValue("data", json_encode($actionData['params']));

        if (!$this->entityLoader->save($actionEntity)) {
            throw new \RuntimeException("Could not save action");
        }

        $actionToSave->setId($actionEntity->getEntityId());
        $actionToSave->setGuid($actionEntity->getEntityId());

        // Save child actions
        $this->saveActions(
            $actionToSave->getActions(),
            $actionToSave->getRemovedActions(),
            $workflowGuid,
            $actionToSave->getWorkFlowLegacyActionId()
        );

        return false;
    }

    /**
     * Schedule an action to run at some time in the future
     *
     * @param int $workFlowInstanceId
     * @param int $actionId
     * @param \DateTime $executeTime
     * @return bool true on success, false on failure
     */
    public function scheduleAction($workFlowInstanceId, $actionId, \DateTime $executeTime)
    {
        if (!is_numeric($workFlowInstanceId) || !is_numeric($actionId)) {
            throw new \InvalidArgumentException("The first two params must be numeric");
        }

        $scheduleData = [
            "action_id" => $actionId,
            "ts_execute" => $executeTime->format("Y-m-d g:i a T"),
            "instance_id" => $workFlowInstanceId
        ];

        $entity = $this->entityLoader->create(ObjectTypes::WORKFLOW_ACTION_SCHEDULED);
        $entity->fromArray($scheduleData);
        $scheduleId = $this->entityLoader->save($entity);

        return ($scheduleId) ? true : false;
    }

    /**
     * Delete a scheduled action if set for a workflow instance and an action
     *
     * @param int $workFlowInstanceId
     * @param int $actionId
     * @return bool true on success, false on failure
     */
    public function deleteScheduledAction($workFlowInstanceId, $actionId)
    {
        if (!is_numeric($workFlowInstanceId) || !is_numeric($actionId)) {
            throw new \InvalidArgumentException("The first two params must be numeric");
        }

        $query = new EntityQuery(ObjectTypes::WORKFLOW_ACTION_SCHEDULED);
        $query->where("action_id")->equals($actionId);
        $query->andWhere("instance_id")->equals($workFlowInstanceId);
        $result = $this->entityIndex->executeQuery($query);

        if ($result->getNum()) {
            $entity = $result->getEntity($i);
            $this->entityLoader->delete($entity);
            return true;
        }

        return false;
    }

    /**
     * Get a scheduled action time if set for a workflow instance and an action
     *
     * @param int $workFlowInstanceId
     * @param int $actionId
     * @return \DateTime|null
     */
    public function getScheduledActionTime($workFlowInstanceId, $actionId)
    {
        if (!is_numeric($workFlowInstanceId) || !is_numeric($actionId)) {
            throw new \InvalidArgumentException("The first two params must be numeric. $workFlowInstanceId and $actionId was provided.");
        }

        $query = new EntityQuery(ObjectTypes::WORKFLOW_ACTION_SCHEDULED);
        $query->where("action_id")->equals($actionId);
        $query->andWhere("instance_id")->equals($workFlowInstanceId);

        $result = $this->entityIndex->executeQuery($query);

        if ($result->getNum()) {
            $entity = $result->getEntity($i);
            $strTime = $entity->getValue("ts_execute");

            // $strTime should always be set, but you can never be too careful
            if (!$strTime) {
                return null;
            }

            $scheduledTime = new DateTime();
            $scheduledTime->setTimestamp($strTime);

            // We should have a valid time from the PGSQL timestamp column, return the new date
            return $scheduledTime;
        }

        // Action is not scheduled
        return null;
    }

    /**
     * Get all actions scheduled to be executed on or before $toDate
     *
     * @param \DateTime $toDate
     * @return array(array("instance"=>WorkFlowLegacyInstance, "action"=>ActionInterface))
     */
    public function getScheduledActions(\DateTime $toDate = null)
    {
        // Return array
        $actions = [];

        // If no date was passed use now
        if ($toDate === null) {
            $toDate = new \DateTime();
        }

        $query = new EntityQuery(ObjectTypes::WORKFLOW_ACTION_SCHEDULED);
        $query->where("ts_execute")->isLessOrEqualTo($toDate->format("Y-m-d g:i a T"));
        $result = $this->entityIndex->executeQuery($query);
        $num = $result->getNum();

        // Get all scheduled actions
        for ($i = 0; $i < $num; $i++) {
            $entity = $result->getEntity($i);

            $instanceId = $entity->getValue('instance_id');
            $actionId = $entity->getValue('action_id');

            $instance = $this->getWorkFlowLegacyInstanceById($instanceId);
            $action = $this->getActionById($actionId);

            // Only return the scheduled action if the instance and action are still valid
            if ($instance && $action) {
                $actions[] = array(
                    "instance" => $instance,
                    "action" => $action,
                );
            } else {
                // It looks like either the action was deleted or the instance was cancelled, cleanup
                $this->deleteScheduledAction($instanceId, $actionId);
            }
        }

        return $actions;
    }

    /**
     * Load up an action by id
     *
     * This is not a public function, it is used for internal functions only right now
     *
     * @param $actionId
     * @return ActionInterface
     */
    private function getActionById($actionId)
    {
        if (!$actionId || !is_numeric($actionId)) {
            throw new \InvalidArgumentException("First param is required to load an action");
        }

        $actionEntity = $this->entityLoader->getByGuid($actionId);
        if ($actionEntity) {
            $workflowData = $actionEntity->toArray();

            /*
             * If we have field_data in our result, it means that we have already implemented the new definiton schema
             * where we eliminated definition columns and put all the entity data in the field_data column
             * Since field_data is json encoded, we need to decode it so we can properly retrieve the entity data.
             */
            if (!empty($workflowData['field_data'])) {
                $workflowData = json_decode($workflowData['field_data'], true);
            }

            $actionArray = array(
                "id" => $workflowData['id'],
                "guid" => $workflowData['guid'],
                "name" => $workflowData['name'],
                "workflow_id" => $workflowData['workflow_id'],
                "type" => $workflowData['type_name'],
                "parent_action_id" => $workflowData['parent_action_id'],
                "child_actions" => $this->getActionsArray($workflowData['workflow_id'], $workflowData['guid']),
            );

            // TODO: get child actions

            // Get params
            if ($workflowData['data']) {
                $actionArray['params'] = json_decode($workflowData['data'], true);
            }

            // Create action from data
            $action = $this->actionFactory->create($actionArray['type']);
            $action->fromArray($actionArray);
            return $action;
        }

        // Not found
        return null;
    }
}