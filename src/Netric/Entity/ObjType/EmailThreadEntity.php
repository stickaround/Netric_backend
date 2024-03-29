<?php

/**
 * @author Sky Stebnicki <sky.stebnicki@aereus.com>
 * @copyright 2015 Aereus
 */

namespace Netric\Entity\ObjType;

use Netric\Entity\Entity;
use Netric\Entity\EntityInterface;
use Netric\Entity\EntityLoader;
use Netric\EntityQuery\EntityQuery;
use Netric\EntityQuery\Index\IndexInterface;
use Netric\ServiceManager\ServiceLocatorInterface;
use Netric\EntityDefinition\ObjectTypes;
use Netric\Entity\ObjType\UserEntity;
use Netric\EntityDefinition\EntityDefinition;
use Netric\EntityGroupings\GroupingLoader;

/**
 * Email thread extension
 */
class EmailThreadEntity extends Entity implements EntityInterface
{
    /**
     * Entity query index for finding threads
     *
     * @var IndexInterface
     */
    private $entityIndex = null;

    /**
     * Class constructor
     *
     * @param EntityDefinition $def The definition of this type of object
     * @param EntityLoader $entityLoader Loader to get/save entities
     * @param IndexInterface $entityIndex Index to query entities
     */
    public function __construct(
        EntityDefinition $def,
        EntityLoader $entityLoader,
        GroupingLoader $groupingLoader,
        IndexInterface $entityIndex
    ) {
        $this->entityIndex = $entityIndex;

        parent::__construct($def, $entityLoader, $groupingLoader);
    }

    /**
     * Callback function used for derived subclasses
     *
     * @param ServiceLocatorInterface $serviceLocator ServiceLocator for injecting dependencies
     * @param UserEntity $user The user that is acting on this entity
     */
    public function onAfterSave(ServiceLocatorInterface $serviceLocator, UserEntity $user)
    {
        // Check it see if the user deleted the whole thread
        if ($this->isArchived()) {
            $this->removeMessages(false, $user);
        } elseif ($this->fieldValueChanged("f_deleted")) {
            // Check if we un-deleted the thread
            $this->restoreMessages();
        }
    }

    /**
     * Called right before the entity is purged (hard delete)
     *
     * @param ServiceLocatorInterface $serviceLocator ServiceLocator for injecting dependencies
     * @param UserEntity $user The user that is acting on this entity
     */
    public function onAfterDeleteHard(ServiceLocatorInterface $serviceLocator, UserEntity $user)
    {
        // Purge all messages that were in this thread
        $this->removeMessages(true, $user); // Now purge
    }

    /**
     * Merge an address or comma separated list of addresses to the senders list
     *
     * @param string $senders
     */
    public function addToSenders($senders)
    {
        $this->mergeAddressesWithField("senders", $senders);
    }

    /**
     * Merge an address or comma separated list of addresses to receivers list
     *
     * @param string $receivers
     */
    public function addToReceivers($receivers)
    {
        $this->mergeAddressesWithField("receivers", $receivers);
    }

    /**
     * Merge an address or comma separated list of addresses to a field
     *
     * @param string $fieldName The name of the field we are updating
     * @param string $addresses Comma separated list of addresses to add
     */
    private function mergeAddressesWithField($fieldName, $addresses)
    {
        // Combine existing with the new
        $newAddresses = explode(",", $addresses);

        // Trim
        for ($i = 0; $i < count($newAddresses); $i++) {
            $newAddresses[$i] = trim($newAddresses[$i]);
        }

        $oldAddresses = ($this->getValue($fieldName)) ? explode(",", $this->getValue($fieldName)) : [];
        $combined = array_merge($newAddresses, $oldAddresses);

        // Make the receivers unique so we only see a name once
        $combined = array_unique($combined);

        // Update value
        $this->setValue($fieldName, implode(",", $combined));
    }

    /**
     * Remove all messages in this thread
     *
     * @param bool $hard Flag to indicate if we should just soft delete (save with flag) or purge
     */
    private function removeMessages($hard, UserEntity $user)
    {
        if (!$this->getEntityId()) {
            return;
        }

        $query = new EntityQuery(ObjectTypes::EMAIL_MESSAGE, $user->getAccountId());
        $query->where("thread")->equals($this->getEntityId());
        $results = $this->entityIndex->executeQuery($query);
        $num = $results->getTotalNum();
        for ($i = 0; $i < $num; $i++) {
            $emailMessage = $results->getEntity($i);
            if ($hard) {
                $this->getEntityLoader()->delete($emailMessage, $user);
            } else {
                $this->getEntityLoader()->archive($emailMessage, $user);
            }
        }

        // If we are doing a hard delete, then also get previously deleted
        if ($hard) {
            $query = new EntityQuery(ObjectTypes::EMAIL_MESSAGE, $user->getAccountId());
            $query->where("thread")->equals($this->getEntityId());
            $query->andWhere("f_deleted")->equals(true);
            $results = $this->entityIndex->executeQuery($query);
            $num = $results->getTotalNum();
            for ($i = 0; $i < $num; $i++) {
                $emailMessage = $results->getEntity($i);
                $this->getEntityLoader()->delete($emailMessage, $user);
            }
        }
    }

    /**
     * Restore all soft-deleted messages by setting deleted flag to false
     */
    private function restoreMessages()
    {
        if (!$this->getEntityId()) {
            return;
        }

        $query = new EntityQuery(ObjectTypes::EMAIL_MESSAGE, $this->getAccountId());
        $query->where("thread")->equals($this->getEntityId());
        $query->andWhere("f_deleted")->equals(true);
        $results = $this->entityIndex->executeQuery($query);
        $num = $results->getTotalNum();
        for ($i = 0; $i < $num; $i++) {
            $emailMessage = $results->getEntity($i);
            $emailMessage->setValue("f_deleted", false);
            $messageUser = $this->getEntityLoader()->getEntityById(
                $emailMessage->getValue('owner_id'),
                $this->getAccountId()
            );
            $this->getEntityLoader()->save($emailMessage, $messageUser);
        }
    }
}
