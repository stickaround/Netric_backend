<?php

namespace Netric\Entity;

use Netric\Stats\StatsPublisher;
use Netric\Cache\CacheInterface;
use Netric\EntityDefinition\EntityDefinitionLoader;
use Netric\Entity\DataMapper\EntityDataMapperInterface;
use Netric\Entity\ObjType\UserEntity;
use Netric\EntityDefinition\EntityDefinition;

/**
 * Entity service used to get/save/delete entities
 */
class EntityLoader
{
    /**
     * The maximum number of entities to keep loaded in memory
     */
    const MAX_LOADED = 1000;

    /**
     * Cached entities
     *
     * @var EntityInterface[]
     */
    private $loadedEntities = [];

    /**
     * Used to cache unames
     *
     * @var array
     */
    private array $unameToId = [];

    /**
     * Datamapper for entities
     *
     * @var EntityDataMapperInterface
     */
    private $dataMapper = null;

    /**
     * Entity definition loader for getting definitions
     *
     * @var EntityDefinitionLoader
     */
    private $definitionLoader = null;

    /**
     * Entity factory used for instantiating new entities
     *
     * @var \Netric\Entity\EntityFactory
     */
    protected $entityFactory = null;

    /**
     * Cache
     *
     * @var CacheInterface
     */
    private $cache = null;

    /**
     * Class constructor
     *
     * @param EntityDataMapperInterface $dataMapper The entity datamapper
     * @param EntityDefinitionLoader $defLoader The entity definition loader
     * @param EntityFactory $entityFactory
     * @param CacheInterface $cache
     */
    public function __construct(
        EntityDataMapperInterface $dataMapper,
        EntityDefinitionLoader $defLoader,
        EntityFactory $entityFactory,
        CacheInterface $cache
    ) {
        $this->dataMapper = $dataMapper;
        $this->definitionLoader = $defLoader;
        $this->entityFactory = $entityFactory;
        $this->cache = $cache;
    }

    /**
     * Determine if an entity is already cached in memory
     *
     * @param string $entityId
     * @return bool true if the entity was already loaded into memory, false if not
     */
    private function isLoaded(string $entityId)
    {
        return (!empty($this->loadedEntities[$entityId])) ? true : false;
    }

    /**
     * Determine if an entity is in the cache layer
     *
     * @param string $objType The type of objet we are loading
     * @param string $id
     * @return array|bool Array of data if cached or false if nut found
     */
    private function getCached(string $entityId)
    {
        $key = "entity/" . $entityId;
        return $this->cache->get($key);
    }

    /**
     * Get an entity by the global universal ID (no need for obj_type)
     *
     * @param string $entityId
     * @param string $accountId
     * @return EntityInterface|null
     */
    public function getEntityById(string $entityId, string $accountId): ?EntityInterface
    {
        if ($this->isLoaded($entityId)) {
            return $this->loadedEntities[$entityId];
        }

        // First check to see if the object is cached
        $data = $this->getCached($entityId);
        if (!empty($data) && isset($data['obj_type'])) {
            $entity = $this->create($data['obj_type'], $accountId);
            $entity->fromArray($data);
            if ($entity->getEntityId()) {
                // Clear dirty status
                $entity->resetIsDirty();

                // Save in loadedEntities so we don't hit the cache again
                $this->loadedEntities[$entityId] = $entity;

                // Stat a cache hit
                StatsPublisher::increment("entity.cache.hit");

                return $entity;
            }
        }

        // Stat a cache miss
        StatsPublisher::increment("entity.cache.miss");

        // Load from datamapper
        $entity = $this->dataMapper->getEntityById($entityId, $accountId);
        if ($entity) {
            // Keep the first MAX_LOADED in local memory since it is the fastest
            if (count($this->loadedEntities) < self::MAX_LOADED) {
                $this->loadedEntities[$entityId] = $entity;
            }

            // Use network cache layer
            $this->cache->set(
                "entity/" . $entityId,
                $entity->toArray()
            );
            return $entity;
        }

        // Could not be loaded
        return null;
    }

    /**
     * Get an entity by a unique name path
     *
     * Unique names can be namespaced, and we can reference entities with a full
     * path since the namespace can be a parentField. For example, the 'page' entity
     * type has a unique name namespace of parentId so we could path
     * uuid-page:uname
     *
     * @param string $objType The entity to populate if we find the data
     * @param string $uniqueNamePath The path to the entity
     * @param string $accountId The current accountId
     * @return EntityInterface $entity if found or null if not found
     */
    public function getByUniqueName(string $objType, string $uniqueNamePath, string $accountId)
    {
        $cacheKey = $objType . $accountId . $uniqueNamePath;
        if (isset($this->unameToId[$cacheKey])) {
            return $this->getEntityById($this->unameToId[$cacheKey], $accountId);
        }

        // Get the entity
        $entity = $this->dataMapper->getByUniqueName($objType, $uniqueNamePath, $accountId);

        // Handle caching here since this function can be expensive
        if ($entity && count($this->unameToId) < self::MAX_LOADED) {
            $this->unameToId[$cacheKey] = $entity->getEntityId();
            $this->loadedEntities[$entity->getEntityId()] = $entity;
        }

        return $entity;
    }

    /**
     * Shortcut for constructing an Entity
     *
     * @param string $definitionName The name of the entity definition
     * @param string $accountId The account ID that will own the entity
     * @return EntityInterface
     */
    public function create(string $definitionName, string $accountId)
    {
        return $this->entityFactory->create($definitionName, $accountId);
    }

    /**
     * Save an entity
     *
     * @param EntityInterface $entity The entity to save
     * @param UserEntity Entity with user details
     * @return int|string|null Id of entity saved or null on failure
     */
    public function save(EntityInterface $entity, UserEntity $user, $logActivity = false)
    {
        $ret = $this->dataMapper->save($entity, $user, $logActivity);

        // Also clear the cache for entity id
        if ($ret) {
            $this->clearCacheByGuid($ret);
        }

        return $ret;
    }

    /**
     * Save an entity
     *
     * @param EntityInterface $entity The entity to delete
     * @param UserEntity Entity with user details
     * @return bool True on success, false on failure
     */
    public function delete(EntityInterface $entity, UserEntity $user)
    {
        $this->clearCacheByGuid($entity->getEntityId());

        return $this->dataMapper->delete($entity, $user);
    }

    /**
     * Flag entity as archived but don't actually delete it
     *
     * @param EntityInterface $entity The entity to delete
     * @param UserEntity Entity with user details
     * @return bool True on success, false on failure
     */
    public function archive(EntityInterface $entity, UserEntity $user)
    {
        $this->clearCacheByGuid($entity->getEntityId());

        return $this->dataMapper->archive($entity, $user);
    }

    /**
     * Clear cache by guid
     *
     * @param string $guid The guid of an entity
     */
    public function clearCacheByGuid(string $guid)
    {
        if ($guid) {
            $this->loadedEntities[$guid] = null;
            $this->cache->remove("entity/$guid");
        }
    }

    /**
     * Get Revisions for this object
     *
     * @param string $objType The name of the object type to get
     * @param string $guid The unique id of the object to get revisions for
     * @return array("revisionNum"=>Entity)
     */
    public function getRevisions(string $entityId, string $accountId): array
    {
        return $this->dataMapper->getRevisions($entityId, $accountId);
    }

    /**
     * Set this object as having been moved to another object
     *
     * @param string $fromId The id to move
     * @param string $toId The unique id of the object this was moved to
     * @param string $accountId The account we are updating
     * @return bool true on succes, false on failure
     */
    public function setEntityMovedTo(string $fromId, string $toId, string $accountId): bool
    {
        return $this->dataMapper->setEntityMovedTo($fromId, $toId, $accountId);
        $this->clearCacheByGuid($fromId);
        $this->clearCacheByGuid($toId);
    }

    /**
     * Get an entity definition
     *
     * @param string $type
     * @param string $accountId
     * @return EntityDefinition|null
     */
    public function getEntityDefinitionByName(string $type, string $accountId): ?EntityDefinition
    {
        return $this->definitionLoader->get($type, $accountId);
    }
}
