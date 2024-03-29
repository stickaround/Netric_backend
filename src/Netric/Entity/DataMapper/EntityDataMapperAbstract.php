<?php

namespace Netric\Entity\DataMapper;

use Netric\Entity\ActivityLog;
use Netric\Entity\EntityAggregator;
use Netric\Entity\Notifier\Notifier;
use Netric\Entity\Validator\EntityValidator;
use Netric\EntityDefinition\EntityDefinitionLoader;
use Netric\Entity\Recurrence\RecurrenceIdentityMapper;
use Netric\EntityGroupings\GroupingLoader;
use Netric\EntityQuery\Index\IndexInterface;
use Netric\EntityQuery\EntityQuery;
use Netric\EntitySync\Commit\CommitManager;
use Netric\EntitySync\EntitySync;
use Netric\EntityDefinition\EntityDefinition;
use Netric\EntityDefinition\Field;
use Netric\Entity\EntityInterface;
use Netric\Entity\EntityFactory;
use Netric\DataMapperAbstract;
use Netric\ServiceManager\ServiceLocatorInterface;
use Ramsey\Uuid\Uuid;
use Netric\Entity\EntityAggregatorFactory;
use Netric\Entity\EntityEvents;
use Netric\Entity\ObjType\UserEntity;
use Netric\EntityQuery\Index\IndexFactory;
use Netric\PubSub\PubSubInterface;
use Netric\Stats\StatsPublisher;
use Netric\WorkerMan\WorkerService;
use Netric\WorkerMan\Worker\EntityPostSaveWorker;
use Netric\WorkerMan\Worker\EntitySyncSetExportedStaleWorker;
use RuntimeException;

/**
 * A DataMapper is responsible for writing and reading data from a persistant store
 */
abstract class EntityDataMapperAbstract extends DataMapperAbstract
{
    /**
     * Commit manager used to crate global commits for sync
     *
     * @var CommitManager
     */
    protected CommitManager $commitManager;

    /**
     * Recurrence Identity Mapper
     *
     * @var RecurrenceIdentityMapper
     */
    private RecurrenceIdentityMapper $recurIdentityMapper;

    /**
     * Caches the results on checking if entity has moved
     *
     * @var array
     */
    private array $cacheMovedEntities = [];

    /**
     * Entity sync manager
     *
     * @var EntitySync
     */
    private EntitySync $entitySync;

    /**
     * Entity validator
     *
     * @var EntityValidator
     */
    private EntityValidator $entityValidator;

    /**
     * Entity Index
     *
     * @var IndexInterface
     */
    private IndexInterface $entityIndex;

    /**
     * Factory to create new entities
     *
     * @var EntityFactory
     */
    private EntityFactory $entityFactory;

    /**
     * Service used to send notifications based on changes
     *
     * @var Notifier
     */
    private Notifier $notifierService;

    /**
     * Aggregator service to aggregate values automatically
     *
     * @var EntityAggregator
     */
    private EntityAggregator $entityAggregator;

    /**
     * Used to load entity definitions
     *
     * @var EntityDefinitionLoader
     */
    private EntityDefinitionLoader $entityDefLoader;

    /**
     * Activity logger
     *
     * @var ActivityLog
     */
    private ActivityLog $activityLog;

    /**
     * Loader for groupings
     *
     * @var GroupingLoader
     */
    private GroupingLoader $groupingLoader;

    /**
     * Account service manager
     *
     * NOTE: passing this in is very bad! We should use dependency injection
     * instead and only use the service manager in factories. We are only using it
     * for an iterative refactor to pass to entity callbacks and will eventually
     * need to be removed. DO NOT USE IT FOR ANYTHING NEW.
     */
    private ServiceLocatorInterface $serviceManager;

    /**
     * Used to schedule background jobs
     */
    private WorkerService $workerService;

    /**
     * PubSub service for emitting important events about entities
     */
    private PubSubInterface $pubSub;

    /**
     * Class constructor
     *
     * @param RecurrenceIdentityMapper $recurIdentityMapper
     * @param CommitManager $commitManager
     * @param EntityValidator $entityValidator
     * @param EntityFactory $entityFactory
     * @param EntityDefinitionLoader $entityDefLoader
     * @param GroupingLoader $groupingLoader
     * @param ServiceLocatorInterface $serviceManager
     * @param WorkerSErvice $workerService
     * @param PubSubInterface $pubSub
     */
    public function __construct(
        RecurrenceIdentityMapper $recurIdentityMapper,
        CommitManager $commitManager,
        EntityValidator $entityValidator,
        EntityFactory $entityFactory,
        EntityDefinitionLoader $entityDefLoader,
        GroupingLoader $groupingLoader,
        ServiceLocatorInterface $serviceManager,
        WorkerService $workerService,
        PubSubInterface $pubSub
    ) {
        $this->recurIdentityMapper = $recurIdentityMapper;
        $this->commitManager = $commitManager;
        $this->entityValidator = $entityValidator;
        $this->entityFactory = $entityFactory;
        $this->entityDefLoader = $entityDefLoader;
        $this->groupingLoader = $groupingLoader;
        $this->serviceManager = $serviceManager;
        $this->workerService = $workerService;
        $this->pubSub = $pubSub;
    }

    /**
     * Set this entity id as having been moved to another entity (merged)
     *
     * @param string $fromId The id to move
     * @param string $toId The unique id of the object this was moved to
     * @param string $accountId The ID of the account we are updating
     * @return bool true on success, false on failure
     */
    abstract public function setEntityMovedTo(
        string $fromId,
        string $toId,
        string $accountId
    ): bool;

    /**
     * Get entity data by guid
     *
     * @param string $entityId
     * @param string $accountid
     * @return array|null
     */
    abstract protected function fetchDataByEntityId(string $entityId, string $accountId): ?array;

    /**
     * Delete entity perminantly
     *
     * @var EntityInterface $entity The entity to delete
     * @var string $accountId the Account to delete
     * @return bool true on success, false on failure
     */
    abstract protected function deleteHard(EntityInterface $entity, string $accountId): bool;

    /**
     * Save entity
     *
     * @param EntityInterface $entity The entity to save
     * @throws \RuntimeException If there is a problem saving to the database
     */
    abstract protected function saveData(EntityInterface $entity): string;

    /**
     * Check if an entity has moved
     *
     * @param string $entityId The id of the entity that no longer exists - may have moved
     * @param string $accountId The id of the account that owns the entity that potentiall moved
     * @return string New entity id if moved, otherwise empty string
     */
    abstract protected function entityHasMoved(string $entityId, string $accountId): string;

    /**
     * Save revision snapshot
     *
     * @param EntityInterface $entity The entity to save
     * @return string|bool entity id on success, false on failure
     */
    abstract protected function saveRevision($entity);

    /**
     * Get historical values for this entity saved on each revision
     *
     * @param string $entityId
     * @param string $accountId
     * @return array Field data that can be used in EntityInterface::fromArray
     */
    abstract protected function getRevisionsData(string $entityId, string $accountId): array;

    /**
     * Call used to generate a unique ID for a uname field
     *
     * We use this because a UUID used for entity IDs is not really human-readable
     * so this is a per-account unique ID generator to make it easier for internal
     * users to share entities with numbers.
     *
     * @param string $accountId
     * @return string
     */
    abstract protected function generateUnameId(string $accountId): string;

    /**
     * Save entity data
     *
     * @param EntityInterface $entity The entity to save
     * @param UserEntity $user The user that is acting on this entity
     * @param bool $logActivity If true, we'll create an activity log for this action
     * @return string entity id on success, false on failure
     */
    public function save(EntityInterface $entity, UserEntity $user, bool $logActivity = false): string
    {
        // Make sure the user is valid - must have account_id and either an entityId or be anonymous or system
        if (
            empty($user->getAccountId()) ||
            (empty($user->getEntityId()) && !$user->isAnonymous() && !$user->isSystem())
        ) {
            throw new RuntimeException(
                'A valid user must be set before saving an entity.'
            );
        }

        $def = $entity->getDefinition();

        // First make sure this entity is valid
        if (!$this->entityValidator->isValid($entity, $this)) {
            $this->errors = array_merge($this->errors, $this->entityValidator->getErrors());
            return false;
        }

        // Increment revision for this save
        $revision = $entity->getValue("revision");
        $revision = (!$revision) ? 1 : ++$revision;
        $entity->setValue("revision", $revision);

        // Make sure account ID is set
        if (empty($entity->getValue('account_id'))) {
            $entity->setValue('account_id', $user->getAccountId());
        }

        // Create new global commit revision
        $lastCommitId = (int) $entity->getValue('commit_id');
        $commitId = $this->commitManager->createCommit("entities/" . $def->getObjType());
        $entity->setValue('commit_id', $commitId);

        /*
         * If the revision value is greater than 1, then set the $event to EVENT_UPDATE otherwise EVENT_CREATE
         * 
         * Make sure we set the ts_updated or ts_created to null to make sure that 
         * server's current time will be set when calling the ::setFieldsDefault()
         * 
         * This will make sure that all entities being saved will have the same timezone, and we will not have
         * any issues sorting them by date
         */
        if ($revision > 1) {
            $event = EntityEvents::EVENT_UPDATE;
            $entity->setValue("ts_updated", null);
        } else {
            $event = EntityEvents::EVENT_CREATE;
            $entity->setValue("ts_entered", null);
            $entity->setValue("ts_updated", null);
        }

        // Set defaults including ts_updated and ts_entered
        $entity->setFieldsDefault($event, $user);

        // Create a unique name or human-readable number - UUIDs are hard to remember
        $this->setUniqueName($entity);

        // Create global uuid if not already set
        $this->setGlobalId($entity);

        // Update foreign key names
        $this->updateForeignKeyNames($entity, $user);

        /*
         * If the entity has a new recurrence pattern, then we need to get the next recurring id
         * now so we can save it to the entity before saving the recurring patterns itself.
         * This is the result of a circular reference where the recurrence pattern has a
         * reference to the first entity id, and the entity has a reference to the recurrence
         * pattern. We might want to come up with a better overall solution. - Sky Stebnicki
         */
        $useRecurId = null;
        if ($entity->getRecurrencePattern() && $def->recurRules) {
            if (!$entity->getValue($def->recurRules['field_recur_id'])) {
                $useRecurId = $this->recurIdentityMapper->getNextId();
                $entity->getRecurrencePattern()->setId($useRecurId);
                $entity->setValue($def->recurRules['field_recur_id'], $useRecurId);
            }
        }

        // Call beforeSave
        $entity->beforeSave($this->serviceManager, $user);

        // Get releveant changed description
        $changedDesc = $entity->getChangeLogDescription();

        // Save data to DataMapper implementation
        $ret = $this->saveData($entity);

        // Save revision for historical reference
        if ($def->storeRevisions) {
            $this->saveRevision($entity);
        }

        // Log the change in entity sync
        if ($ret && $lastCommitId && $commitId) {
            $this->workerService->doWorkBackground(EntitySyncSetExportedStaleWorker::class, [
                'account_id' => $user->getAccountId(),
                'collection_type' => EntitySync::COLL_TYPE_ENTITY,
                'last_commit_id' => $lastCommitId,
                'new_commit_id' => $commitId
            ]);
        }

        // Call onAfterSave
        $entity->afterSave($this->serviceManager, $user);

        // Update any aggregates that could be impacted by saving $entity
        $this->entityAggregator = $this->serviceManager->get(EntityAggregatorFactory::class);
        $this->entityAggregator->updateAggregates($entity, $user);

        // Send saved event to pubsub (redis) so that other services can respond
        // One primary use of this is the api.netric.com server will watch for entity
        // changes on behalf of the client to send updates for things like badges and
        // changes to notifications.
        $this->pubSub->publish(
            'netric/' . $entity->getAccountId() . '/entity_change',
            [
                'action' => $event,
                'before' => ($event === EntityEvents::EVENT_UPDATE) ? $this->getDataBeforeChanges($entity) : [],
                'after' => $entity->toArray()
            ]
        );

        // Reset dirty flag and changelog
        $entity->resetIsDirty();

        /*
         * If this is part of a recurring series - which means it has a recurrence pattern -
         * and not an exception, then save the recurrence pattern.
         */
        if (!$entity->isRecurrenceException() && $entity->getRecurrencePattern()) {
            $this->recurIdentityMapper->saveFromEntity($entity, $useRecurId);
        }

        // Send background job to do less expedient (but no less important) tasks
        // We check for user id because system or anonymous users do not have an ID
        if ($user->getEntityId() && $ret) {
            $this->workerService->doWorkBackground(EntityPostSaveWorker::class, [
                'account_id' => $user->getAccountId(),
                'user_id' => $user->getEntityId(),
                'entity_id' => $ret,
                'event_name' => $event,
                'changed_description' => $changedDesc,
                'log_activity' => $logActivity,
            ]);
        }

        // Publish write stats for monitoring
        StatsPublisher::increment("entity.datamapper,function=save,obj_type=" . $def->getObjType());

        return $ret;
    }

    /**
     * Get an entity by id
     *
     * @param string $entityId The unique id of the entity to load
     * @param string $accountId
     * @return EntityInterface|null
     */
    public function getEntityById(string $entityId, string $accountId): ?EntityInterface
    {
        $data = $this->fetchDataByEntityId($entityId, $accountId);

        if (!$data || empty($data['obj_type'])) {
            return null;
        }

        $entity = $this->entityFactory->create($data['obj_type'], $accountId);
        $entity->fromArray($data);

        // Load a recurrence pattern if set
        if ($entity->getDefinition()->recurRules) {
            // If we have a recurrence pattern id then load it
            // NOTE: we filter out non-uuid IDs because we changed
            $recurId = $entity->getValue($entity->getDefinition()->recurRules['field_recur_id']);
            if ($recurId && !is_numeric($recurId)) {
                $recurPattern = $this->recurIdentityMapper->getById($recurId, $accountId);
                if ($recurPattern) {
                    $entity->setRecurrencePattern($recurPattern);
                }
            }
        }

        // Reset dirty flag and changelog since we just loaded
        $entity->resetIsDirty();

        // Publish read stats for monitoring
        StatsPublisher::increment("entity.datamapper,function=getEntityById");

        return $entity;
    }

    /**
     * Get an entity by a unique name path
     *
     * Unique names can be namespaced, and we can reference entities with a full
     * path since the namespace can be a parentField. For example, the 'page' entity
     * type has a unique name namespace of parentId so we could path /page1/page2/page1
     * and the third page1 is a different entity than the first.
     *
     * @param string $objType The entity to populate if we find the data
     * @param string $uniqueNamePath The path to the entity
     * @param string $accountId Current account ID
     * @param array $namespaceFieldValues Optional array of filter values for unique name namespaces
     * @return EntityInterface $entity if found or null if not found
     */
    public function getByUniqueName(
        string $objType,
        string $uniqueNamePath,
        string $accountId
    ): ?EntityInterface {
        // Publish read stats for monitoring
        StatsPublisher::increment("entity.datamapper,function=getByUniqueName");

        // $def = $this->entityDefLoader->get($objType, $accountId);

        // // Sanitize in case the user passed in bad paths like '/my//path'
        // $uniqueNamePath = str_replace("//", "/", $uniqueNamePath);

        // // Remove a trailing '/'
        // if ($uniqueNamePath[strlen($uniqueNamePath) - 1] === '/') {
        //     $uniqueNamePath = substr($uniqueNamePath, 0, -1);
        // }

        // // Remove a root '/'
        // if ($uniqueNamePath[0] === '/') {
        //     $uniqueNamePath = substr($uniqueNamePath, 1);
        // }

        // // Now split the full sanitized path into segments
        // $segments = explode("/", $uniqueNamePath);

        // // Pop the uname of the current level off the path
        // $uname = array_pop($segments);

        // $filterValues = array_merge(
        //     $namespaceFieldValues,
        //     ['uname' => $uname]
        // );
        // $matches = $this->getIdsFromFieldValues($objType, $filterValues, $accountId);

        // // Return the first match
        // if (!empty($matches[0])) {
        //     $entity = $this->getEntityById($matches[0], $accountId);
        //     return $entity;
        // }

        // Search objects to see if the uname exists
        $query = new EntityQuery($objType, $accountId);
        $query->where('uname')->equals($uniqueNamePath);

        // Query for matching IDs
        $this->entityIndex = $this->serviceManager->get(IndexFactory::class);
        $result = $this->entityIndex->executeQuery($query);
        if ($result->getTotalNum() > 0) {
            return $result->getEntity(0);
        }

        // Could not find a unique match
        return null;
    }

    // /**
    //  * Look for IDs based on field values
    //  *
    //  * @param string $objType The type of entity we are querying
    //  * @param array $conditionValues Array of field values to query for
    //  * @param string $accountId Current account ID
    //  * @return string[] Array of IDs that match the field values
    //  */
    // private function getIdsFromFieldValues($objType, array $conditionValues, string $accountId)
    // {
    //     $entityIds = [];

    //     // Search objects to see if the uname exists
    //     $query = new EntityQuery($objType, $accountId);

    //     foreach ($conditionValues as $fieldName => $fieldCondValue) {
    //         $query->andWhere($fieldName)->equals($fieldCondValue);
    //     }

    //     // Query for matching IDs
    //     $this->entityIndex = $this->serviceManager->get(IndexFactory::class);
    //     $result = $this->entityIndex->executeQuery($query);
    //     for ($i = 0; $i < $result->getTotalNum(); $i++) {
    //         $entity = $result->getEntity($i);
    //         $entityIds[] = $entity->getEntityId();
    //     }

    //     return $entityIds;
    // }

    /**
     * Delete an entity
     *
     * @param EntityInterface $entity The entity to delete
     * @param UserEntity $user user entity
     * @return bool true on success, false on failure
     */
    public function delete(EntityInterface $entity, UserEntity $user): bool
    {
        $lastCommitId = $entity->getValue("commit_id");
        // Create new global commit revision
        $commitId = $this->commitManager->createCommit("entities/" . $entity->getDefinition()->getObjType());

        // Call beforeDeleteHard so the entity can do any pre-purge operations
        $entity->beforeDeleteHard($this->serviceManager, $user);

        // Purge the recurrence pattern if set
        if ($entity->getRecurrencePattern()) {
            // Only delete the recurrence pattern if this is the original
            if ($entity->getRecurrencePattern()->entityIsFirst($entity)) {
                $this->recurIdentityMapper->delete($entity->getRecurrencePattern());
            }
        }

        // Perform the delete from the data store
        $ret = $this->deleteHard($entity, $user->getAccountId());

        // Call onBeforeDeleteHard so the entity can do any post-purge operations
        $entity->afterDeleteHard($this->serviceManager, $user);

        // Delete from EntityCollection_Index
        $this->entityIndex = $this->serviceManager->get(IndexFactory::class);
        $this->entityIndex->delete($entity);

        // Determine if we are flagging the entity as deleted or actually purging
        // if ($entity->getValue("f_deleted")) {

        // } else {
        //     $entity->setValue('commit_id', $commitId);
        //     $ret = null;
        //     $ret = $this->archive($entity, $user);

        //     // Delete from EntityCollection_Index
        //     //$this->getServiceLocator()->get("EntityCollection_Index")->delete($entity);

        //     // Log the activity
        //     $this->activityLog->log($user, "delete", $entity);
        // }

        // Log the change in entity sync
        if ($ret && $lastCommitId && $commitId) {
            $this->workerService->doWorkBackground(EntitySyncSetExportedStaleWorker::class, [
                'account_id' => $user->getAccountId(),
                'collection_type' => EntitySync::COLL_TYPE_ENTITY,
                'last_commit_id' => $lastCommitId,
                'new_commit_id' => $commitId
            ]);
        }

        // Send delete event to pubsub (redis) so that other services can respond
        // One primary use of this is the api.netric.com server will watch for entity
        // changes on behalf of the client to send updates for things like badges and
        // changes to notifications.
        $this->pubSub->publish(
            'netric/' . $entity->getAccountId() . '/entity_change',
            [
                'action' => EntityEvents::EVENT_DELETE,
                'before' => $entity->toArray(),
                'after' => [] // no more
            ]
        );

        return $ret;
    }

    /**
     * Flag data as archived but don't actually delete it
     *
     * @var EntityInterface $entity The entity to load data into
     * @var UserEntity $user The current user
     * @return bool true on success, false on failure
     */
    public function archive(EntityInterface $entity, UserEntity $user): bool
    {
        // Update the deleted flag and save
        $entity->setValue("f_deleted", true);
        $ret = $this->save($entity, $user);
        return ($ret === false) ? false : true;
    }

    /**
     * Update foreign key name cache
     *
     * All foreign key (fkey, fkey_multi, object, object_multi) fields
     * cache the name of the foreign key for faster performance.
     *
     * @param EntityInterface $entity The entity to update
     * @param UserEntity $user Current user
     */
    private function updateForeignKeyNames(EntityInterface $entity, UserEntity $user)
    {
        $fields = $entity->getDefinition()->getFields();
        foreach ($fields as $field) {
            $value = $entity->getValue($field->name);

            // Skip over null/empty fields
            if (!$value) {
                continue;
            }

            switch ($field->type) {
                case Field::TYPE_GROUPING:
                case Field::TYPE_OBJECT:
                    // If we are dealing with null or empty id, then there is no need to update this foreign key
                    if (!Uuid::isValid($value)) {
                        continue 2;
                    }

                    $name = $entity->getNameForReferencedId($field->name, $value);

                    // Since we have found the referenced entity, then add it in the entity
                    $entity->setValue($field->name, $value, $name);
                    break;

                case Field::TYPE_GROUPING_MULTI:
                case Field::TYPE_OBJECT_MULTI:
                    $value = (is_array($value)) ? array_filter($value) : [$value];
                    foreach ($value as $id) {
                        // If we are dealing with null or empty id, then there is no need to update this foreign key
                        if (!$id || !Uuid::isValid($id)) {
                            continue;
                        }

                        $name = $entity->getNameForReferencedId($field->name, $id);

                        // // If we havent found the referenced entity, chances are it was already removed,
                        // // so we need to clear the value
                        // if (!$name) {
                        //     $entity->removeMultiValue($field->name, $id);
                        //     continue;
                        // }

                        // Since we have found the referenced entity, then add it in the entity
                        if ($name) {
                            $entity->addMultiValue($field->name, $id, $name);
                        }
                    }

                    break;
            }
        }
    }

    /**
     * When saving an entity create a unqiue name if not already set
     *
     * @param EntityInterface $entity
     * @return bool true if changed, false if failed
     */
    private function setUniqueName(EntityInterface $entity)
    {
        $def = $entity->getDefinition();

        // If we have already created a uname and saved it then do nothing
        // We add this to reduce load since there's no point in validating
        // a uname over and over is nothing has changed since it was last saved
        if ($entity->getValue("uname") && !$entity->fieldValueChanged('uname')) {
            return false;
        } elseif ($entity->getValue("uname")) {
            // Uname has changed - either manually or automatically with settings
            if (!$this->verifyUniqueName($entity, $entity->getValue("uname"))) {
                // Uhoh, they entered a uname that already exists
                // prepend a unique ID
                $uname = $entity->getValue('uname');
                $uname .= "-";
                $uname .= $this->generateUnameId($entity->getAccountId());
                $entity->setValue('uname', $uname);
                return true;
            }

            // Use the new name when saving
            return false;
        }

        // Eitehr the object type does not have unameSettings (using a field for the name)
        // the entity failed to set it for some reason, just use a unique umber
        $unameNumber = $this->generateUnameId($entity->getAccountId());
        $entity->setValue('uname', $unameNumber);
        return true;

        // return $this->setUniqueNameFromSettings($entity, $def->unameSettings);
    }

    /**
     * Automatically create a uname from an entity value
     *
     * This is usually used for things with unique human names like users where
     * uname=name or blog posts which are url friendly version of the title
     *
     * @param EntityInterface $entity
     * @param string $unameSettings
     * @return bool
     */
    private function setUniqueNameFromSettings(EntityInterface $entity, string $unameSettings): bool
    {
        $unameSettingsParts = explode(":", $unameSettings);

        // Create desired uname from the right field
        // Format is: "field_name:field_name"
        $lastFieldName = end($unameSettingsParts);

        // Get the name of the entity
        $uname = ($lastFieldName === "name") ? $entity->getName() : $entity->getValue($lastFieldName);

        // If fields that would contain data for uname are blank
        // then just provide a unique number, this should be rare
        if (!$uname) {
            $uname = $this->generateUnameId($entity->getAccountId());
            $entity->setValue("uname", $uname);
            return true;
        }

        // Prefix with the namespace if set
        $numParts = count($unameSettingsParts);
        if ($numParts > 1) {
            // We start at the end and work our way backwards since
            // we are progressively prefixing the field value
            // and we subtract 2 from numparts because we don't want to
            // add the uname of the current entity (already set above)
            for ($i = $numParts - 2; $i >= 0; $i--) {
                $fieldName = $unameSettingsParts[$i];
                $uname = $entity->getValue($fieldName) . ':' . $uname;
            }
        }

        // Now escape the uname field to a uri friendly name
        // since we use this for URLs in a few places
        $uname = strtolower($uname);
        $uname = str_replace(" ", "-", $uname);
        $uname = str_replace("&", "_and_", $uname);
        $uname = str_replace("@", "_at_", $uname);
        $uname = preg_replace('/[^A-Za-z0-9:._-]/', '', $uname);

        // If the unique name already exists, then append with id to assure uniqueness
        if (!$this->verifyUniqueName($entity, $uname)) {
            $uname .= "-";
            $uname .= $this->generateUnameId($entity->getAccountId());
        }

        // Set the uname
        $entity->setValue("uname", $uname);
        return true;
    }

    /**
     * Make sure that a uname is still 7unique
     *
     * This should safe-gard against values being saved in the object that change the namespace
     * of the unique name causing unique collision.
     *
     * @param EntityInterface $entity The entity to save
     * @param string $uname The name to test for uniqueness
     * @return bool true if the uniqueName is truly unique or false if there is a collision
     */
    public function verifyUniqueName($entity, $uname)
    {
        $def = $entity->getDefinition();

        // If we are not using unique names with this object just succeed
        if (!$def->unameSettings) {
            return true;
        }

        // Search objects to see if the uname exists
        $query = new EntityQuery($def->getObjType(), $entity->getAccountId());
        $query->where("uname")->equals($uname);

        // Exclude this object from the query because of course it will be a duplicate
        if ($entity->getEntityId()) {
            $query->andWhere("entity_id")->doesNotEqual($entity->getEntityId());
        }

        /*
         * Loop through all namespaces if set with ':' in the settings
         * The first part of the settings is "<opt_namespace_field>:"
         * and it can have as many namespaces as needed with the last entry
         * being the entity field that is used to generate the unique name.
         */
        $nsParts = explode(":", $def->unameSettings);
        if (count($nsParts) > 1) {
            // Use all but last, which is the uname field
            for ($i = 0; $i < (count($nsParts) - 1); $i++) {
                $query->andWhere($nsParts[$i])->equals($entity->getValue($nsParts[$i]));
            }
        }

        // Check if any objects match
        $this->entityIndex = $this->serviceManager->get(IndexFactory::class);
        $result = $this->entityIndex->executeQuery($query);

        if ($result->getTotalNum() > 0) {
            return false;
        }

        // Name is unique
        return true;
    }

    /**
     * Make sure the entity has a global unique id (create it if not)
     *
     * @param EntityInterface $entity
     */
    private function setGlobalId(EntityInterface $entity)
    {
        if (!$entity->getValue('entity_id')) {
            $uuid4 = Uuid::uuid4();
            $entity->setValue('entity_id', $uuid4->toString());
        }
    }

    /**
     * Check if an object has moved
     *
     * @param EntityDefinition $def The defintion of this object type
     * @param string $localId The id of the object that no longer exists - may have moved
     * @param string $accountId Account that owns the potentially moved eneity
     * @return string New entity id if moved, otherwise false
     */
    public function checkEntityHasMoved(EntityDefinition $def, string $localId, string $accountId): string
    {
        $cachedId = $def->getObjType() . "-" . $localId;

        /*
         * If we have already checked this entity, then return the result
         * If the cached result is empty, then will try to check again if the entity has been moved now
         */
        if (isset($this->cacheMovedEntities[$cachedId])) {
            return $this->cacheMovedEntities[$cachedId];
        }

        // Check if entity has moved
        $movedToId = $this->entityHasMoved($localId, $accountId);

        // Store the result in the cache
        if ($movedToId) {
            $this->cacheMovedEntities[$cachedId] = $movedToId;
        }

        return $movedToId;
    }

    /**
     * Get Revisions for this object
     *
     * @param string $entityId The unique id of the entity to get revisions for
     * @param string $accountId
     * @return array("revisionNum"=>Entity)
     */
    public function getRevisions(string $entityId, string $accountId): array
    {
        $ret = [];

        if (!$entityId || !$accountId) {
            return $ret;
        }

        $revisionData = $this->getRevisionsData($entityId, $accountId);
        foreach ($revisionData as $revId => $entityFieldData) {
            $entity = $this->entityFactory->create($entityFieldData['obj_type'], $accountId);
            $entity->fromArray($entityFieldData);
            $ret[$revId] = $entity;
        }

        return $ret;
    }

    /**
     * Get a data representation of what the entity looked like before it was changed (last save)
     *
     * @param EntityInterface $entity
     * @return array
     */
    private function getDataBeforeChanges(EntityInterface $entity): array
    {
        // First thing to do is check if we saved a brand new entity
        if ($entity->fieldValueChanged('entity_id')) {
            // All values changed since it was a new entity
            return [];
        }

        // Determine what we changed
        $data = $entity->toArray();
        $definition = $entity->getDefinition();
        $fields = $definition->getFields();
        foreach ($fields as $field) {
            if ($entity->fieldValueChanged($field->getName())) {
                $data[$field->getName()] = $entity->getPreviousValue($field->getName());
            }
        }

        return $data;
    }
}
