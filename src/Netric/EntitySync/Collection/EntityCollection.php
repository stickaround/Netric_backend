<?php

declare(strict_types=1);

namespace Netric\EntitySync\Collection;

use Netric\EntityQuery\Index;
use Netric\EntitySync\DataMapperInterface;
use Netric\EntitySync\EntitySync;
use Netric\EntitySync\Commit;
use Netric\EntitySync\Commit\CommitManager;
use Netric\EntityQuery\Index\IndexInterface;
use Netric\EntityQuery\EntityQuery;
use Netric\WorkerMan\WorkerService;
use Netric\Db\Relational\RelationalDbContainer;
use DateTime;

/**
 * Class used to represent a sync partner or endpoint
 */
class EntityCollection extends AbstractCollection implements CollectionInterface
{
    /**
     * Index for querying entities
     *
     * @var IndexInterface
     */
    private $index = null;

    /**
     * Relational database collectionDataMapper for Entity Sync Collection
     *
     * @var CollectionDataMapperInterface
     */
    private $collectionDataMapper = null;

    /**
     * Constructor
     *
     * @param CommitManager $commitManager A manager used to keep track of commits
     * @param IndexInterface $index Index for querying entities
     * @param WorkerService $workerService Used to schedule background jobs
     * @param CollectionDataMapperInterface $collectionDataMapper Relational database dataMapper for Entity Sync Collection
     */
    public function __construct(
        CommitManager $commitManager,
        IndexInterface $index,
        WorkerService $workerService,
        CollectionDataMapperInterface $collectionDataMapper
    ) {
        $this->index = $index;
        $this->collectionDataMapper = $collectionDataMapper;

        // Pass commitManager to parent
        parent::__construct($commitManager, $workerService, $collectionDataMapper);
    }

    /**
     * Get a stats list of what has changed locally since the last sync
     *
     * @param bool $autoFastForward If true (default) then fast-forward collection commit_id on return
     * @param \DateTime $limitUpdatesAfter If set, only pull updates after a specific date
     * @return array of associative array [
     *      [
     *          "id", // Unique id of local object
     *          "action", // 'change'|'delete',
     *          "commit_id" // Incremental id of the commits - global revision
     *      ]
     *  ]
     * @throws \Exception if the objType was not set
     */
    public function getExportChanged($autoFastForward = true, DateTime $limitUpdatesAfter = null)
    {
        if (!$this->getObjType()) {
            throw new \Exception("Object type not set! Cannot export changes.");
        }

        if (!$this->getAccountId()) {
            throw new \Exception("Account is not set! Cannot export changes.");
        }

        // Set return array
        $retStats = [];

        // Get the current commit for this collection
        $lastCollectionCommit = $this->getLastCommitId();
        if ($lastCollectionCommit === null) {
            $lastCollectionCommit = 0;
        }

        if ($this->isBehindHead()) {
            // Query local objects for commit_id with EntityList
            $query = new EntityQuery($this->getObjType(), $this->getAccountId());
            $query->orderBy('commit_id');
            $query->setLimit(1000);

            // Set base/common condition
            $query->where('commit_id')->isGreaterThan($lastCollectionCommit);
            $query->andWhere('commit_id')->doesNotEqual('');

            // We need to have an OR condition for f_deleted = true, so we can include the archived entities
            $query->orWhere('f_deleted')->equals(true);

            // Check to see if we should only pull updates after a specific date
            if ($limitUpdatesAfter) {
                $query->andWhere("ts_updated")->isGreaterOrEqualTo($limitUpdatesAfter->getTimestamp());
            }

            // Add any collection conditions
            $conditions = $this->getConditions();
            foreach ($conditions as $cond) {
                if ($cond['blogic'] == 'or') {
                    $query->orWhere($cond['field'], $cond['operator'], $cond['condValue']);
                } else {
                    $query->andWhere($cond['field'], $cond['operator'], $cond['condValue']);
                }
            }

            // Execute query and get num results
            $res = $this->index->executeQuery($query);
            $num = $res->getNum();

            /*
             * Get previously imported so we do not try to export a recent import.
             * Only get list if there are entities to export to save time
             */
            if ($this->getCollectionId() && $num) {
                $imports = $this->collectionDataMapper->getImported($this->getCollectionId(), $this->getAccountId());
            } else {
                $imports = [];
            }

            // Loop through each change
            for ($i = 0; $i < $num; $i++) {
                $ent = $res->getEntity($i);

                // First make sure we didn't just import this
                $skipStat = false;
                foreach ($imports as $imported) {
                    if ($imported['local_id'] == $ent->getEntityId() &&
                        $imported['local_revision'] == $ent->getValue("commit_id")
                    ) {
                        // Skip over this export because we just imported it
                        $skipStat = true;
                        break;
                    }
                }

                if (!$skipStat) {
                    $retStats[] = [
                        "id" => $ent->getEntityId(),
                        "action" => (($ent->isArchived()) ? 'delete' : 'change'),
                        "commit_id" => $ent->getValue("commit_id")
                    ];

                    // Sanity check, make sure we do not return the last commit id for change again
                    if ($ent->getValue("commit_id") == $lastCollectionCommit) {
                        throw new \Exception(
                            "ERROR: Trying to return the commit previously returned: " .
                                $lastCollectionCommit
                        );
                    }
                }

                if (($autoFastForward || $skipStat) && $ent->getValue("commit_id")) {
                    // Fast-forward $lastCommitId to last commit_id sent
                    $this->setLastCommitId($ent->getValue("commit_id"));

                    // Save to exported log
                    $this->logExported(
                        $ent->getEntityId(),
                        $ent->getValue("commit_id")
                    );
                }
            }

            /*
             * If no new changes were found, then get previously exported
             * objects that have been updated but apparently no longer meet
             * the conditions of this collection.
             *
             * Only do this if we have conditions that might have moved an entity
             * outside of a subset of entities (query). If all entities are being
             * returned by this collection then every change will be replayed in order
             * by the above query.
             */
            if (0 == count($retStats)) {
                $retStats = $this->getExportedStale();
                if ($autoFastForward) {
                    foreach ($retStats as $stale) {
                        // Save to exported log with no commit deletes the export
                        $this->logExported($stale['id']);
                    }
                }
            }

            /*
             * If we found no changes since the last commit, then fast-forward
             * to keep us at the head of the collection.
             * This prevents a bug where $this->isBehindHead can report true
             * suggesting there might be changes, but querying reveals none.
             */
            if (0 === count($retStats)) {
                $this->fastForwardToHead();
            }

            // TODO: Save lastCommit if changed
            if (count($retStats) && $autoFastForward && $this->getCollectionId()) {
                // saveCollection is currently private, research...
                // $this->dataMapper->saveCollection($this);
            }
        }

        return $retStats;
    }

    /**
     * Get a collection type id
     *
     * @return int Type from \Netric\EntitySync::COLL_TYPE_*
     */
    public function getType()
    {
        return EntitySync::COLL_TYPE_ENTITY;
    }

    /**
     * Fast forward this collection to current head which resets it to only get future changes
     */
    public function fastForwardToHead()
    {
        $headCommitId = $this->getCollectionTypeHeadCommit();

        if ($headCommitId) {
            $this->setLastCommitId($headCommitId);
        }
    }

    /**
     * Load collection data from an associative array
     *
     * @param array $data
     */
    public function fromArray($data)
    {
        if (isset($data['entity_sync_collection_id'])) {
            $this->setCollectionId($data['entity_sync_collection_id']);
        }

        if (isset($data['object_type'])) {
            $this->setObjType($data['object_type']);
        }

        if (isset($data['field_id'])) {
            $this->setFieldId($data['field_id']);
        }

        if (isset($data['field_name'])) {
            $this->setFieldName($data['field_name']);
        }

        if (isset($data['ts_last_sync'])) {
            $this->setLastSync(new DateTime($data['ts_last_sync']));
        }

        if (isset($data['conditions'])) {
            $this->setConditions($data['conditions']);
        }

        if (isset($data['revision'])) {
            $this->setRevision($data['revision']);
        }

        if (isset($data['last_commit_id'])) {
            $this->setLastCommitId($data['last_commit_id']);
        }
    }

    /**
     * Get the head commit for a given collection type
     *
     * @return string The last commit id for the type of data we are watching
     */
    protected function getCollectionTypeHeadCommit()
    {
        return $this->commitManager->getHeadCommit("entities/" . $this->getObjType());
    }
}
