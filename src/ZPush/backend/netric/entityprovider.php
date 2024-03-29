<?php

/**
 * Provider to convert entities into sync objects
 *
 * The reason all the files are lowercase in here is because that is the z-push standard
 * so we stick with it to be consistent.
 */

$zPushRoot = dirname(__FILE__) . "/../../";

// Interfaces we are extending
require_once($zPushRoot . 'lib/interface/ibackend.php');
require_once($zPushRoot . 'lib/interface/isearchprovider.php');

// Supporting files and exceptions
require_once($zPushRoot . 'lib/core/zpush.php');
require_once($zPushRoot . 'lib/request/request.php');
require_once($zPushRoot . 'lib/exceptions/authenticationrequiredexception.php');
require_once($zPushRoot . 'lib/exceptions/statusexception.php');

// processing of RFC822 messages
require_once($zPushRoot . 'include/mimeDecode.php');
require_once($zPushRoot . 'include/z_RFC822.php');

// Include netric autoloader for all netric libraries
require_once(dirname(__FILE__) . "/../../../../vendor/autoload.php");

use Netric\Entity\Recurrence\RecurrencePattern;
use Netric\Entity\EntityInterface;
use Netric\Log\LogInterface;
use Netric\Account\Account;
use Netric\Entity\ObjType\UserEntity;
use Netric\FileSystem\FileSystemFactory;
use Netric\Entity\EntityLoaderFactory;
use Netric\EntityDefinition\ObjectTypes;
use Netric\EntityGroupings\GroupingLoaderFactory;
use Netric\EntityQuery\Index\IndexFactory;
use Netric\EntityQuery\EntityQuery;

/**
 * Save and load sync objects from netric entities
 */
class EntityProvider
{
    /**
     * Constants representing types of folders for objects we sync
     */
    const FOLDER_TYPE_EMAIL = 1;
    const FOLDER_TYPE_TASK = 2;
    const FOLDER_TYPE_CONTACT = 3;
    const FOLDER_TYPE_NOTE = 4;
    const FOLDER_TYPE_CALENDAR = 5;

    /**
     * Current account/tenant
     *
     * @var Account
     */
    private $account = null;

    /**
     * Current netric user
     *
     * @var UserEntity
     */
    private $user = null;

    /**
     * EntityLoader for getting and saving entities
     *
     * @var \Netric\EntityLoader
     */
    private $entityLoader = null;

    /**
     * Log
     *
     * @var LogInterface
     */
    private $log = null;

    /**
     * Setup the provider
     *
     * @param Account $account
     * @param UserEntity $user
     * @param LogInterface $log Optional log to use if we don't want to use the application log
     */
    public function __construct(Account $account, UserEntity $user, LogInterface $log = null)
    {
        $this->account = $account;
        $this->user = $user;
        $this->entityLoader = $account->getServiceManager()->get(EntityLoaderFactory::class);
        $this->log = ($log) ? $log : $account->getApplication()->getLog();
    }

    /**
     * Get the account set for this entity provider
     *
     * @return Account
     */
    public function getAccount(): Account
    {
        return $this->account;
    }

    /**
     * Returns all available data of a single message
     *
     * @param string $folderId Encoded folder string which contains the obj-type and folder id
     * @param string $id Unique id of the entity to get
     * @param ContentParameters $contentParameters Flags for customizing the return values
     * @return SyncObject
     */
    public function getSyncObject($folderId, $id, $contentParameters)
    {

        $folder = $this->unpackFolderId($folderId);

        switch ($folder['type']) {
            case self::FOLDER_TYPE_TASK:
                return $this->getTask($id, $contentParameters);
            case self::FOLDER_TYPE_EMAIL:
                return $this->getEmail($id, $contentParameters);
            case self::FOLDER_TYPE_CALENDAR:
                return $this->getAppointment($id, $contentParameters);
            case self::FOLDER_TYPE_CONTACT:
                return $this->getContact($id, $contentParameters);
            case self::FOLDER_TYPE_NOTE:
                return $this->getNote($id, $contentParameters);
        }
    }

    /**
     * Save a SyncObject to an entity
     *
     * @param string $folderId Encoded folder string which contains the obj-type and folder id
     * @param string $id Unique id of the entity to get
     * @param SyncObject $syncObject Object to save
     * @return string either the id of success, or null on failure
     */
    public function saveSyncObject($folderId, $id, SyncObject $syncObject)
    {
        $folder = $this->unpackFolderId($folderId);

        switch ($folder['type']) {
            case self::FOLDER_TYPE_TASK:
                return $this->saveTask($id, $syncObject);
            case self::FOLDER_TYPE_EMAIL:
                return $this->saveEmail($id, $syncObject, $folder['id']);
            case self::FOLDER_TYPE_CALENDAR:
                return $this->saveAppointment($id, $syncObject, $folder['id']);
            case self::FOLDER_TYPE_CONTACT:
                return $this->saveContact($id, $syncObject);
            case self::FOLDER_TYPE_NOTE:
                return $this->saveNote($id, $syncObject, $folder['id']);
        }
    }

    /**
     * Move an entity if supported
     *
     * @param string $id Unique id of the entity to move
     * @param string $folderId Encoded folder string which contains the obj-type and folder id
     * @param string $newFolderId Encoded folder string which contains the obj-type and folder id
     * @throws StatusException On major failure
     * @return bool true on success, false if not supported
     */
    public function moveEntity($id, $folderId, $newFolderId)
    {
        $oldFolder = $this->unpackFolderId($folderId);
        $newFolder = $this->unpackFolderId($newFolderId);

        // Make sure we are not trying to move to different types - like from calendar to email_message
        if ($oldFolder['type'] != $newFolder['type']) {
            throw new StatusException("Cannot move entity from type {$oldFolder['type']} to {$newFolder['type']}");
        }

        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);

        switch ($oldFolder['type']) {
            case self::FOLDER_TYPE_EMAIL:
                $entity = $entityLoader->getEntityById($id, $this->account->getAccountId());

                if (!$entity) {
                    return false;
                }

                $entity->setValue("mailbox_id", $newFolder['id']);
                $entityLoader->save($entity, $this->user);
                return true;

            case self::FOLDER_TYPE_CALENDAR:
                $entity = $entityLoader->getEntityById($id, $this->account->getAccountId());

                if (!$entity) {
                    return false;
                }

                $entity->setValue(ObjectTypes::CALENDAR, $newFolder['id']);
                $entityLoader->save($entity, $this->user);
                return true;

            case self::FOLDER_TYPE_CONTACT:
            case self::FOLDER_TYPE_NOTE:
            case self::FOLDER_TYPE_TASK:
            default:
                // Not supported
                $this->log->warning(
                    "ZPUSH->EntityProvider->moveEntity: Tried to move " .
                        $folderId . ":" . $id . " to " . $newFolderId . ":" . $id . " " .
                        "but that is not supported"
                );
                return false;
        }
    }

    /**
     * Get a stat array for an entity
     *
     * @param string $folderId Where the entity is stored - encoded with type and id
     * @param string $id Entity id
     * @return array|bool Stat on success, false on failure
     */
    public function getEntityStat($folderId, $id)
    {
        $folder = $this->unpackFolderId($folderId);
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
        $entity = $this->getEntity($folderId, $id);

        // Return the stat
        if ($entity) {
            return [
                "id" => $entity->getEntityId(),
                "mod" => $entity->getValue("commit_id"),
                "flags" => ($entity->getValue("is_seen") === true) ? 1 : 0,
                "revision" => $entity->getValue("revision")
            ];
        }

        return false;
    }

    /**
     * Get a stat array for an entity
     *
     * @param string $folderId Where the entity is stored - encoded with type and id
     * @param string $id Entity id
     * @return EntityInterface
     */
    public function getEntity($folderId, $id)
    {
        $folder = $this->unpackFolderId($folderId);
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
        $entity = null;

        switch ($folder['type']) {
            case self::FOLDER_TYPE_TASK:
            case self::FOLDER_TYPE_EMAIL:
            case self::FOLDER_TYPE_CALENDAR:
            case self::FOLDER_TYPE_CONTACT:
            case self::FOLDER_TYPE_NOTE:
                $entity = $entityLoader->getEntityById($id, $this->account->getAccountId());
                break;
            default:
                // Not supported
                return false;
        }

        // Return the entity if found
        return $entity;
    }

    /**
     * Mark an entity seen flag
     *
     * @param string $folderId Encoded folder string which contains the obj-type and folder id
     * @param string $id The entity id
     * @param int $flags Seen flag
     * @return bool true on success, false on failure
     */
    public function markEntitySeen($folderId, $id, $flags)
    {
        $folder = $this->unpackFolderId($folderId);
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
        $entity = null;

        switch ($folder['type']) {
            case self::FOLDER_TYPE_TASK:
            case self::FOLDER_TYPE_EMAIL:
            case self::FOLDER_TYPE_CALENDAR:
            case self::FOLDER_TYPE_CONTACT:
            case self::FOLDER_TYPE_NOTE:
                $entity = $entityLoader->getEntityById($id, $this->account->getAccountId());
                break;
            default:
                // Not supported
                return false;
        }

        if ($entity) {
            if ($entity->getDefinition()->getField("flag_seen")) {
                // Support old field that was not standard - email_message included
                $entity->setValue("flag_seen", ($flags) ? true : false);
            } else {
                $entity->setValue("is_seen", ($flags) ? true : false);
            }
            $entityLoader->save($entity, $this->user);
        }

        return true;
    }

    /**
     * Delete an entity
     *
     * @param string $folderId Encoded folder string which contains the obj-type and folder id
     * @param string $id Unique id of the entity to get
     * @return bool true on success, false on failure
     */
    public function deleteEntity($folderId, $id)
    {
        $folder = $this->unpackFolderId($folderId);
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
        $entity = null;

        switch ($folder['type']) {
            case self::FOLDER_TYPE_TASK:
            case self::FOLDER_TYPE_EMAIL:
            case self::FOLDER_TYPE_CALENDAR:
            case self::FOLDER_TYPE_CONTACT:
            case self::FOLDER_TYPE_NOTE:
                $entity = $entityLoader->getEntityById($id, $this->account->getAccountId());
                break;
            default:
                // Not supported
                return false;
        }

        if ($entity) {
            // If the entity was found, delete it and return the results (bool)
            return $entityLoader->archive($entity, $this->user);
        }

        // Not found
        return false;
    }

    /**
     * Delete a folder
     *
     * @param string $folderId Encoded folder string which contains the obj-type and folder id
     * @param string $parentId Optional encoded parent folder string
     * @return bool true on success, false on failure
     */
    public function deleteFolder($folderId, $parentId = null)
    {
        $folder = $this->unpackFolderId($folderId);

        switch ($folder['type']) {
            case self::FOLDER_TYPE_EMAIL:
                $groupingsLoader = $this->account->getServiceManager()->get(GroupingLoaderFactory::class);
                $groupings = $groupingsLoader->get(ObjectTypes::EMAIL_MESSAGE . "/mailbox_id/" . $this->user->getEntityId(), $this->account->getAccountId());

                $group = $groupings->getByGuidOrGroupId($folder['id']);

                if (!$group) {
                    return false;
                }

                // We cannot delete a system folder
                if ($group->isSystem) {
                    $this->log->error("ZPUSH->EntityProvider->deleteFolder: Cannot delete system grouping {$group->name}");
                    return false;
                }

                $groupings->delete($group->getGroupId());
                return $groupingsLoader->save($groupings);

            case self::FOLDER_TYPE_CALENDAR:
                $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
                $entity = $entityLoader->getEntityById($folder['id'], $this->account->getAccountId());

                if (!$entity) {
                    return false;
                }

                return $entityLoader->archive($entity, $this->user);

            case self::FOLDER_TYPE_CONTACT:
            case self::FOLDER_TYPE_NOTE:
            case self::FOLDER_TYPE_TASK:
            default:
                $this->log->warning(
                    "ZPUSH->Entityprovider->deleteFolder: " .
                        "tried to delete $parentId.$folderId which is not supported"
                );
                // Not supported
                return false;
        }
    }

    /**
     * Save changes to a sync folder
     *
     * @param string $parent The optional id of the parent to save
     * @param string $id If saving changes the id to save, otherwise null for new folder
     * @param string $displayname The name of the folder
     * @param int $type Type of folder we are saving
     * @return string|bool Id of saved folder if success, false if failure
     */
    public function saveSyncFolder($parent, $id, $displayname, $type)
    {
        // This is not currently supported
        return false;
    }

    /**
     * Get a folder by id
     *
     * @param string $folderId Encoded folder string which contains the obj-type and folder id
     * @return SyncFolder|bool Folder if exists, false if failure
     */
    public function getFolder($folderId)
    {
        $folder = $this->unpackFolderId($folderId);
        $syncFolders = false;

        switch ($folder['type']) {
            case self::FOLDER_TYPE_TASK:
                $syncFolders = $this->getTaskFolders($folder['id']);
                break;
            case self::FOLDER_TYPE_EMAIL:
                $syncFolders = $this->getEmailFolders($folder['id']);
                break;
            case self::FOLDER_TYPE_CALENDAR:
                $syncFolders = $this->getCalendarFolders($folder['id']);
                break;
            case self::FOLDER_TYPE_CONTACT:
                $syncFolders = $this->getContactFolders($folder['id']);
                break;
            case self::FOLDER_TYPE_NOTE:
                $syncFolders = $this->getNoteFolders($folder['id']);
                break;
        }

        // If folder is in syncFolders, then return it (there will only be one)
        return ($syncFolders) ? $syncFolders[0] : false;
    }

    /**
     * Get all folders merged (hierarchy)
     *
     * @return array SYNC_FOLDER
     */
    public function getAllFolders()
    {
        $emailFolders = $this->getEmailFolders();
        $taskFolders = $this->getTaskFolders();
        $contactFolders = $this->getContactFolders();
        $noteFolders = $this->getNoteFolders();
        $calendarFolders = $this->getCalendarFolders();

        return array_merge(
            $emailFolders,
            $taskFolders,
            $contactFolders,
            $noteFolders,
            $calendarFolders
        );
    }

    /**
     * Get all email groups as folders
     *
     * @param string $id Optional id of specific folder to get
     * @return array SYNC_FOLDER
     */
    public function getEmailFolders($id = "")
    {
        // Log if a user is not provided
        if (!$this->user->getEntityId()) {
            $this->log->error("ZPUSH->EntityProvider->getEmailFolders: Called without a user");
        }

        // Folders to return
        $folders = [];

        /*
         * Get email groupings for email folders.
         * When passing the filer (last param with owner value)
         * the key name is the name of the property in the entity, in this case
         * email_message.owner_id and the value to query for. The entity definition
         * for the grouping will map the entity field value to the grouping value if
         * the names are different like - groupings.owner_id=email_message.owner_id
         */
        $serviceManager = $this->account->getServiceManager();
        $gloader = $serviceManager->get(GroupingLoaderFactory::class);
        $groupings = $gloader->get(ObjectTypes::EMAIL_MESSAGE .  "/mailbox_id/" . $this->user->getEntityId(), $this->account->getAccountId());

        /*
         * For now we are just limiting this to the inbox because apparently the
         * Samsung S8 is having a lot of trouble with the hierarchy
         * TODO: Comments this out to get all folders again
         */
        //$groups = $groupings->getAll();
        $group = $groupings->getByName("Inbox");
        if (!$group) {
            return $folders;
        }

        $groups = [$group];
        foreach ($groups as $group) {
            $folder = new SyncFolder();
            $folder->serverid = $this->packFolderId(self::FOLDER_TYPE_EMAIL, $group->getGroupId());
            $folder->parentid = ($group->parentId) ? $this->packFolderId(self::FOLDER_TYPE_EMAIL, $group->parentId) : "0";
            $folder->displayname = mb_convert_encoding($group->name, "UTF-8", "UTF-7");

            $lowerName = strtolower($group->name);
            if ($lowerName === "inbox" && !$group->parentId && $group->isSystem) {
                $folder->type = SYNC_FOLDER_TYPE_INBOX;
            } elseif ($lowerName === "drafts" && !$group->parentId) {
                $folder->type = SYNC_FOLDER_TYPE_DRAFTS;
            } elseif ($lowerName === "trash" && !$group->parentId) {
                $folder->type = SYNC_FOLDER_TYPE_WASTEBASKET;
            } elseif ($lowerName === "sent" && !$group->parentId) {
                $folder->type = SYNC_FOLDER_TYPE_WASTEBASKET;
            } else {
                $folder->type = SYNC_FOLDER_TYPE_OTHER;
            }
            $folders[] = $folder;

            // If we are only trying to get one id, return it if found
            if ($id == $group->getGroupId()) {
                return [$folder];
            }
        }

        return $folders;
    }

    /**
     * Return a single folder representing all the users tasks
     *
     * @param string $id Optional id, but does not matter since there's only one
     * @return array SYNC_FOLDER
     */
    public function getTaskFolders($id = "")
    {
        $folder = new SyncFolder();
        $folder->serverid = $this->packFolderId(self::FOLDER_TYPE_TASK, "my");
        $folder->parentid = "0";
        $folder->displayname = "My Tasks";
        $folder->type = SYNC_FOLDER_TYPE_TASK;
        return [$folder];
    }

    /**
     * Return a single folder representing all the users contacts
     *
     * @param string $id Optional id, but does not matter since there's only one
     * @return array SYNC_FOLDER
     */
    public function getContactFolders($id = "")
    {
        $folder = new SyncFolder();
        $folder->serverid = $this->packFolderId(self::FOLDER_TYPE_CONTACT, "my");
        $folder->parentid = "0";
        $folder->displayname = "My Contacts";
        $folder->type = SYNC_FOLDER_TYPE_CONTACT;
        return [$folder];
    }

    /**
     * Return a single folder representing all the users notes
     *
     * @param string $id Optional id, but does not matter since there's only one
     * @return array SYNC_FOLDER
     */
    public function getNoteFolders($id = "")
    {
        /*
        // Folders to return
        $folders = array();

        // Add all notes - root condition with no ID
        $folder = new SyncFolder();
        $folder->serverid = $this->packFolderId(self::FOLDER_TYPE_NOTE, "all");
        $folder->parentid = "0";
        $folder->displayname = "Notes";
        $folder->type = SYNC_FOLDER_TYPE_NOTE;
        $folders[] = $folder;

        // Check to see if we were just trying to get the 'all notes' folder
        if ($id == "all") {
            return $folders;
        }

        // Get note groupings
        $serviceManager = $this->account->getServiceManager();
        $gloader = $serviceManager->get(GroupingLoaderFactory::class);
        $groupings = $gloader->get(
            ObjectTypes::NOTE,
            "groups",
            array("owner_id"=>$this->user->getEntityId())
        );

        $groups = $groupings->getAll();
        foreach ($groups as $group)
        {
            $folder = new SyncFolder();
            $folder->type = SYNC_FOLDER_TYPE_NOTE;
            $folder->serverid = $this->packFolderId(self::FOLDER_TYPE_NOTE, $group->getGroupId());
            $folder->parentid = ($group->parentId) ? $this->packFolderId(self::FOLDER_TYPE_NOTE, $group->parentId) : 0;
            $folder->displayname = mb_convert_encoding($group->name, "UTF-8", "UTF-7");
            $folders[] = $folder;

            // If we are only trying to get one id, return it if found
            if ($id == $group->getGroupId()) {
                return array($folder);
            }
        }


        return $folders;
         */

        // Rather than returning all the groupings as folders (above), we are using categories
        // because iPhone didn't like that many groupings
        $folder = new SyncFolder();
        $folder->serverid = $this->packFolderId(self::FOLDER_TYPE_NOTE, "my");
        $folder->parentid = "0";
        $folder->displayname = "My Notes";
        $folder->type = SYNC_FOLDER_TYPE_NOTE;
        return [$folder];
    }

    /**
     * Return user's calendars as sync folders
     *
     * @param string $id Optional id of specific folder to get
     * @return array SYNC_FOLDER
     */
    public function getCalendarFolders($id = "")
    {
        // Folders to return
        $folders = [];

        // Setup the query
        $query = new EntityQuery(ObjectTypes::CALENDAR, $this->account->getAccountId());
        $query->where("owner_id")->equals($this->user->getEntityId());

        // Check fi we are only supposed to get a single calendar
        if ($id) {
            $query->andWhere("id")->equals($id);
        }

        // Execute the query
        $serviceManager = $this->account->getServiceManager();
        $index = $serviceManager->get(IndexFactory::class);
        $results = $index->executeQuery($query);
        $num = $results->getNum();
        for ($i = 0; $i < $num; $i++) {
            $calendar = $results->getEntity($i);
            $folder = new SyncFolder();
            $folder->serverid = $this->packFolderId(self::FOLDER_TYPE_CALENDAR, $calendar->getEntityId());
            $folder->parentid = "0";
            $folder->displayname = mb_convert_encoding($calendar->getName(), "UTF-8", "UTF-7");
            $folder->type = SYNC_FOLDER_TYPE_APPOINTMENT;
            $folders[] = $folder;
        }

        return $folders;
    }

    /**
     * Get a contact from ANT and convert to SyncObjet
     *
     * @param string $id The unique id of the contact to get
     * @param ContentParameters $contentParameters flag
     * @return SyncContact
     */
    private function getContact($id, $contentParameters)
    {
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
        $contactEntity = $entityLoader->getEntityById($id, $this->account->getAccountId());

        $contact = new SyncContact();
        $contact->email1address = $contactEntity->getValue('email');
        $contact->firstname = $contactEntity->getValue('first_name');
        $contact->lastname = $contactEntity->getValue('last_name');
        $contact->mobilephonenumber = $contactEntity->getValue('phone_cell');
        $contact->nickname = $contactEntity->getValue('nick_name');
        $contact->email2address = $contactEntity->getValue('email2');
        $contact->email3address = $contactEntity->getValue('email_spouse');
        $contact->businessphonenumber = $contactEntity->getValue('phone_work');
        $contact->businesscity = $contactEntity->getValue('business_city');
        //$contact->businesscountry = $row['businesscountry'];
        $contact->businesspostalcode = $contactEntity->getValue('business_zip');
        $contact->businessstate = $contactEntity->getValue('business_state');
        $contact->businessstreet = $contactEntity->getValue('business_street');
        //$contact->categories = $row['categories'];
        $contact->companyname = $contactEntity->getValue('company');
        $contact->homecity = $contactEntity->getValue('city');
        $contact->homepostalcode = $contactEntity->getValue('postal_code');
        $contact->homestate = $contactEntity->getValue('state');
        $contact->homestreet = $contactEntity->getValue('street');
        $contact->homefaxnumber = $contactEntity->getValue('fax');
        $contact->homephonenumber = $contactEntity->getValue('phone_home');
        $contact->jobtitle = $contactEntity->getValue('job_title');
        $contact->middlename = $contactEntity->getValue('middle_name');
        $contact->pagernumber = $contactEntity->getValue('pager');
        $contact->spouse = $contactEntity->getValue('spouse_name');

        /*
        $contact->body = $contactEntity->getValue('notes');
        $contact->bodysize = strlen($contactEntity->getValue('notes'));
        $contact->bodytruncated = 0;
         */

        $this->log->info("ZPUSH->EntityProvider->getContact: returning " . $contactEntity->getEntityId());

        return $contact;
    }

    /**
     * Get a calendar event from ANT and convert to SyncObjet
     *
     * @param string $id The unique id of the event to get
     * @param ContentParameters $contentParameters flag
     * @return SyncAppointment
     */
    private function getAppointment($id, $contentParameters)
    {
        $cur_tz = date_default_timezone_get();

        // Commented out while we figure things out
        //$pulltz = ($this->user->timezoneName) ? $this->user->timezoneName : 'utc';
        $pulltz = 'utc';

        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
        $entityEvent = $entityLoader->getEntityById($id, $this->account->getAccountId());

        if (!$entityEvent->getValue("ts_end") || !$entityEvent->getValue("ts_start")) {
            return false;
        }

        /*
        if ($appointment->starttime)
            $entityEvent->setValue("ts_start", date("Y-m-d g:i A", $appointment->starttime));
        if ($appointment->endtime)
            $entityEvent->setValue("ts_end", date("Y-m-d g:i A", $appointment->endtime));
         */

        $appt = new SyncAppointment();
        $appt->subject = $entityEvent->getValue("name");
        $appt->location = $entityEvent->getValue("location");
        $appt->body = $entityEvent->getValue("notes");
        $appt->alldayevent = ($entityEvent->getValue("all_day") == 't') ? true : false;
        $appt->starttime = $entityEvent->getValue("ts_start");
        $appt->endtime = $entityEvent->getValue("ts_end");
        $appt->dtstamp = $entityEvent->getValue("ts_changed");
        $appt->busystatus = 2;
        $appt->meetingstatus = 0;

        // Create timezone
        $tz = TimezoneUtil::GetFullTZ();
        if (!$tz) {
            $tz = $this->getGMTTZ();
        }
        $appt->timezone = base64_encode($this->getSyncBlobFromTZ($tz));

        $appt->recurrence = null;
        $appt->reminder = 0;
        $appt->attendees = null;
        $appt->uid = $id;
        $appt->exceptions = null;
        $appt->categories = null;
        $appt->sensitivity = 0;

        if ($entityEvent->getValue('recurrence_pattern')) {
            $appt->recurrence = new SyncRecurrence();
            $rp = $entityEvent->getRecurrencePattern();

            $appt->recurrance->interval = $rp->interval;

            switch ($rp->type) {
                case RECUR_DAILY:
                    $appt->recurrence->type = 0;
                    break;
                case RECUR_WEEKLY:
                    $appt->recurrence->type = 1;
                    if ($rp->dayOfWeekMask & WEEKDAY_SUNDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_SUNDAY;
                    }
                    if ($rp->dayOfWeekMask & WEEKDAY_MONDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_MONDAY;
                    }
                    if ($rp->dayOfWeekMask & WEEKDAY_TUESDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_TUESDAY;
                    }
                    if ($rp->dayOfWeekMask & WEEKDAY_WEDNESDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_WEDNESDAY;
                    }
                    if ($rp->dayOfWeekMask & WEEKDAY_THURSDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_THURSDAY;
                    }
                    if ($rp->dayOfWeekMask & WEEKDAY_FRIDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_FRIDAY;
                    }
                    if ($rp->dayOfWeekMask & WEEKDAY_SATURDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_SATURDAY;
                    }
                    break;
                case RECUR_MONTHLY:
                    $appt->recurrence->type = 2;
                    $appt->recurrence->dayofmonth = $rp->dayOfMonth;
                    break;
                case RECUR_MONTHNTH:
                    $appt->recurrence->type = 3;
                    $appt->recurrence->weekofmonth = $rp->instance;
                    if ($rp->dayOfWeekMask & WEEKDAY_SUNDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_SUNDAY;
                    }
                    if ($rp->dayOfWeekMask & WEEKDAY_MONDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_MONDAY;
                    }
                    if ($rp->dayOfWeekMask & WEEKDAY_TUESDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_TUESDAY;
                    }
                    if ($rp->dayOfWeekMask & WEEKDAY_WEDNESDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_WEDNESDAY;
                    }
                    if ($rp->dayOfWeekMask & WEEKDAY_THURSDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_THURSDAY;
                    }
                    if ($rp->dayOfWeekMask & WEEKDAY_FRIDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_FRIDAY;
                    }
                    if ($rp->dayOfWeekMask & WEEKDAY_SATURDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_SATURDAY;
                    }
                    break;
                case RECUR_YEARLY:
                    $appt->recurrence->type = 5;
                    $appt->recurrence->dayofmonth = $rp->dayOfMonth;
                    break;
                case RECUR_YEARNTH:
                    $appt->recurrence->type = 6;
                    $appt->recurrence->weekofmonth = $rp->instance;
                    if ($rp->dayOfWeekMask & WEEKDAY_SUNDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_SUNDAY;
                    }
                    if ($rp->dayOfWeekMask & WEEKDAY_MONDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_MONDAY;
                    }
                    if ($rp->dayOfWeekMask & WEEKDAY_TUESDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_TUESDAY;
                    }
                    if ($rp->dayOfWeekMask & WEEKDAY_WEDNESDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_WEDNESDAY;
                    }
                    if ($rp->dayOfWeekMask & WEEKDAY_THURSDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_THURSDAY;
                    }
                    if ($rp->dayOfWeekMask & WEEKDAY_FRIDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_FRIDAY;
                    }
                    if ($rp->dayOfWeekMask & WEEKDAY_SATURDAY) {
                        $appt->recurrence->dayofweek = $appt->recurrence->dayofweek | WEEKDAY_SATURDAY;
                    }
                    break;
            }

            // Termination
            if ($rp->dateEnd) {
                $appt->recurrence->until = strtotime($rp->dateEnd);
            }
        }

        $this->log->info("ZPUSH->EntityProvider:getAppointment returning " . $entityEvent->getEntityId());

        return $appt;
    }

    /**
     * Get a task from ANT and convert to SyncObjet
     *
     * @param string $id The unique id of the task to get
     * @param ContentParameters $contentParameters flag
     * @return SyncTask
     */
    private function getTask($id, $contentParameters)
    {
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
        $taskEntity = $entityLoader->getEntityById($id, $this->account->getAccountId());
        $task = new SyncTask();
        $task->subject = $taskEntity->getValue('name');
        $task->body = $taskEntity->getValue('notes');
        $task->complete = ($taskEntity->getValue('is_closed') === true) ? 1 : 0;
        if ($taskEntity->getValue('date_completed')) {
            $task->datecompleted = $taskEntity->getValue('date_completed');
        }
        if ($taskEntity->getValue('deadline')) {
            $task->duedate = $taskEntity->getValue('deadline');
        }
        if ($taskEntity->getValue('start_date')) {
            $task->startdate = $taskEntity->getValue('start_date');
        }

        $this->log->info("ZPUSH->EntityProvider:getTask returning " . $taskEntity->getEntityId());

        return $task;
    }

    /**
     * Get a task from ANT and convert to SyncObjet
     *
     * @param string $id The unique id of the task to get
     * @param ContentParameters $contentParameters flag
     * @return SyncNote
     */
    private function getNote($id, $contentParameters)
    {
        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
        $noteEntity = $entityLoader->getEntityById($id, $this->account->getAccountId());
        $syncNote = new SyncNote();
        $syncNote->messageclass = "IPM.Note";
        $syncNote->subject = $noteEntity->getValue('name');
        $syncNote->asbody = new SyncBaseBody();

        if ($noteEntity->getValue("ts_updated")) {
            $syncNote->lastmodified = $noteEntity->getValue("ts_updated");
        }

        if ($noteEntity->getValue('body') == 'plain') {
            $syncNote->asbody->type = SYNC_BODYPREFERENCE_PLAIN;
        } else {
            $syncNote->asbody->type = SYNC_BODYPREFERENCE_HTML;
        }

        $bodyContent = $noteEntity->getValue('body');
        $syncNote->asbody->data = StringStreamWrapper::Open($bodyContent);
        $syncNote->asbody->estimatedDataSize = strlen($bodyContent);

        if ($noteEntity->getValue('ts_updated')) {
            $syncNote->lastmodified = $noteEntity->getValue('ts_updated');
        }

        // Get categories
        $groups = $noteEntity->getValueNames("groups");
        if (is_array($groups) && count($groups) > 0) {
            $syncNote->categories = [];
            foreach ($groups as $gid => $gname) {
                $syncNote->categories[] = $gname;
            }
        }

        $this->log->info("ZPUSH->EntityProvider->getNote returning " . $noteEntity->getEntityId());

        return $syncNote;
    }

    /**
     * Get an email message from ANT and convert to SyncObjet
     *
     * @param string $id The unique id of the message  to get
     * @param ContentParameters $contentParameters flag
     * @return SyncMail
     */
    private function getEmail($id, $contentParameters)
    {
        $truncsize = Utils::GetTruncSize($contentParameters->GetTruncation());
        $mimesupport = $contentParameters->GetMimeSupport();
        $bodypreference = $contentParameters->GetBodyPreference(); /* fmbiete's contribution r1528, ZP-320 */

        $entityLoader = $this->account->getServiceManager()->get(EntityLoaderFactory::class);
        $emailEntity = $entityLoader->getEntityById($id, $this->account->getAccountId());

        $output = new SyncMail();

        // Try to detect the body preference
        $bpReturnType = SYNC_BODYPREFERENCE_PLAIN;
        if ($bodypreference !== false) {
            $bpReturnType = Utils::GetBodyPreferenceBestMatch($bodypreference); // changed by mku ZP-330
        }

        /*
         * Get the html and plain body
         */
        $htmlBody = $emailEntity->getHtmlBody();
        $plainBody = $emailEntity->getPlainBody();

        // Normalize carriage returns to \r\n
        $htmlBody = str_replace("\n", "\r\n", str_replace("\r", "", $htmlBody));
        $plainBody = str_replace("\n", "\r\n", str_replace("\r", "", $plainBody));

        /*
         * Determine what kind of response we are sending back. If the protocol version
         * is greater than 12, then we set the body of the output to asbody with meta properties
         * such as truncated, a preview string and data. Older devices only support putting
         * the actual string in the body.
         */
        if (Request::GetProtocolVersion() >= 12.0) {
            $output->asbody = new SyncBaseBody();
            $output->asbody->type = $bpReturnType;
            $output->nativebodytype = $bpReturnType;
            $output->asbody->truncated = 0;


            // Set the body based on the preference
            $bodyData = null;
            switch ($bpReturnType) {
                case SYNC_BODYPREFERENCE_PLAIN:
                    $bodyData = $plainBody;
                    break;

                case SYNC_BODYPREFERENCE_HTML:
                    // If the there is no html then force this to be a plain text message
                    if (empty($htmlBody)) {
                        $bodyData = $plainBody;
                        $output->asbody->type = SYNC_BODYPREFERENCE_PLAIN;
                    } else {
                        $bodyData = $htmlBody;
                    }
                    break;

                case SYNC_BODYPREFERENCE_MIME:
                    $bodyData = $emailEntity->toMailMessage()->toString();
                    break;

                case SYNC_BODYPREFERENCE_RTF:
                    $bodyData = base64_encode($plainBody);
                    break;
            }

            // Truncate body, if requested
            if ($truncsize && strlen($bodyData) > $truncsize) {
                $bodyData = Utils::Utf8_truncate($bodyData, $truncsize);
                $output->asbody->truncated = 1;
            }

            // Wrap the body data in a stream
            $output->asbody->data = StringStreamWrapper::Open($bodyData);

            // Set the total size of the body being sent
            $output->asbody->estimatedDataSize = strlen($bodyData);

            // Check if the device is requesting a preview rather than the full body
            $bodyPreference = $contentParameters->BodyPreference($output->asbody->type);
            if (Request::GetProtocolVersion() >= 14.0 && $bodyPreference->GetPreview()) {
                // z-push handles only sending the preview on the first pass
                $previewSize = $bodyPreference->GetPreview();
                $output->asbody->preview = Utils::Utf8_truncate($plainBody, $previewSize);
            }
        } else {
            /*
             * Preserve support for older decides that only support full mime or plain text
             */
            // ASV_2.5
            $output->bodytruncated = 0;
            if ($bpReturnType == SYNC_BODYPREFERENCE_MIME) {
                $original = $emailEntity->toMailMessage()->toString();
                if (strlen($original) > $truncsize) {
                    $output->mimedata = Utils::Utf8_truncate($original, $truncsize);
                    $output->mimetruncated = 1;
                } else {
                    $output->mimetruncated = 0;
                    $output->mimedata = $original;
                }
                $output->mimesize = strlen($output->mimedata);
            } else {
                // truncate body, if requested
                if (strlen($plainBody) > $truncsize) {
                    $output->body = Utils::Utf8_truncate($plainBody, $truncsize);
                    $output->bodytruncated = 1;
                } else {
                    $output->body = $plainBody;
                    $output->bodytruncated = 0;
                }
                $output->bodysize = strlen($output->body);
            }
        }

        $datereceived = $emailEntity->getValue("message_date");
        $tz = TimezoneUtil::GetFullTZ();
        if (!$tz) {
            $tz = $this->getGMTTZ();
        }

        $output->timezone = base64_encode($this->getSyncBlobFromTZ($tz));
        $output->datereceived = $datereceived;
        $output->displayto = $emailEntity->getValue("to");
        $output->importance = ($emailEntity->getValue("priority")) ? preg_replace("/\D+/", "", $emailEntity->getValue("priority")) : null;
        $output->messageclass = "IPM.Note";
        $output->subject = mb_convert_encoding($emailEntity->getValue("subject"), "UTF-8", "UTF-7");
        $output->read = ($emailEntity->getValue("flag_seen") == 't') ? 1 : 0;
        $output->to = $emailEntity->getValue("to");
        $output->cc = $emailEntity->getValue("cc");
        $output->from = $emailEntity->getValue("from");
        $output->reply_to = $emailEntity->getValue("reply_to");
        //$output->threadtopic = ($emailEntity->getValue("thread_topic")) ? $emailEntity->getValue("thread_topic") : NULL;

        // Language Code Page ID: http://msdn.microsoft.com/en-us/library/windows/desktop/dd317756%28v=vs.85%29.aspx
        $output->internetcpid = INTERNET_CPID_UTF8;
        if (Request::GetProtocolVersion() >= 12.0) {
            $output->contentclass = "urn:content-classes:message";
        }

        // Attachments are not needed for MIME messages
        if ($bpReturnType != SYNC_BODYPREFERENCE_MIME) {
            // Attachments are only searched in the top-level part
            $attachments = $emailEntity->getValue("attachments");
            if (is_array($attachments) && count($attachments) > 1) {
                $serviceManager = $this->account->getServiceManager();
                $fileSystem = $serviceManager->get(FileSystemFactory::class);

                if (!isset($output->attachments) || !is_array($output->attachments)) {
                    $output->attachments = [];
                }

                foreach ($attachments as $attId) {
                    $file = $fileSystem->openFileById($attId, $this->account->getAuthenticatedUser());

                    $attachment = new SyncAttachment();
                    $attachment->attsize = $file->getValue('file_size');

                    $attachment->displayname = $file->getValue("name");
                    //$attachment->attname = $folderid . ":" . $id . ":" . $n;
                    $attachment->attname = $file->getEntityId();
                    $attachment->attmethod = 1;
                    // For some reason the below totally broke the iphone, it has been fixed now
                    //$attachment->attoid = isset($part->headers['content-id']) ? $part->headers['content-id'] : "";
                    array_push($output->attachments, $attachment);
                }
            }
        }

        return $output;
    }

    /**
     * Save a contact from a syncObject
     *
     * @param string $id The unique id of the entity to save
     * @param SyncContact $syncContact The data to save
     * @return string|bool id on success, false on failure
     * @throws RuntimeException If there is a problem saving
     */
    private function saveContact($id, SyncContact $syncContact)
    {
        // Either load or create the entity
        $entity = null;
        if ($id) {
            $entity = $this->entityLoader->getEntityById($id, $this->account->getAccountId());
        } else {
            $entity = $this->entityLoader->create(ObjectTypes::CONTACT_PERSONAL, $this->account->getAccountId());
        }

        $entity->setValue('owner_id', $this->user->getEntityId());
        $entity->setValue('first_name', $syncContact->firstname);
        $entity->setValue('last_name', $syncContact->lastname);
        $entity->setValue('middle_name', $syncContact->middlename);
        $entity->setValue('phone_home', $syncContact->homephonenumber);
        $entity->setValue('phone_work', $syncContact->businessphonenumber);
        $entity->setValue('phone_cell', $syncContact->mobilephonenumber);
        $entity->setValue('phone_fax', $syncContact->homefaxnumber);
        $entity->setValue('phone_pager', $syncContact->pagernumber);
        $entity->setValue('email', $syncContact->email1address);
        $entity->setValue('email2', $syncContact->email2address);
        $entity->setValue('street', $syncContact->homestreet);
        $entity->setValue('city', $syncContact->homecity);
        $entity->setValue('state', $syncContact->homestate);
        $entity->setValue('postal_code', $syncContact->homepostalcode);
        $entity->setValue('company', $syncContact->companyname);
        $entity->setValue('job_title', $syncContact->jobtitle);
        $entity->setValue('website', $syncContact->webpage);
        $entity->setValue('notes', $syncContact->body);
        $entity->setValue('spouse_name', $syncContact->spouse);
        $entity->setValue('birthday', $syncContact->birthday);
        $entity->setValue('anniversary', $syncContact->anniversary);
        $entity->setValue('business_street', $syncContact->businessstreet);
        $entity->setValue('business_city', $syncContact->businesscity);
        $entity->setValue('business_state', $syncContact->businessstate);
        $entity->setValue('business_zip', $syncContact->businesspostalcode);
        if (isset($syncContact->picture)) {
            /*
                // TODO: this should just be moved to entity attachments
               $picbinary = base64_decode($contact->picture);
            $picsize = strlen($picbinary);

            if ($picsize)
            {
                $antfs = new CAntFs($this->dbh, $this->user);
                $fldr = $antfs->openFolder("root/Contact Files/$id", true);
                $file = $fldr->createFile($fldr, "profilepic.jpg");
                $size = $file->write($picbinary);
                if ($file->getEntityId())
                {
                    $obj->setValue('image_id', $file->getEntityId());
                }
            }
             */
        }

        $this->log->info("ZPUSH->EntityProvider->saveContact: returning " . $entity->getEntityId() . ':' . $entity->getName());

        return $this->entityLoader->save($entity, $this->user);
    }

    /**
     * Save a note from a syncObject
     *
     * @param string $id The unique id of the entity to save
     * @param SyncNote $syncNote The data to save
     * @param int $groupId Optional group to put the note into
     * @return string|bool id on success, false on failure
     * @throws RuntimeException If there is a problem saving
     */
    private function saveNote($id, SyncNote $syncNote, $groupId = null)
    {
        // Either load or create the entity
        $entity = null;
        if ($id) {
            $entity = $this->entityLoader->getEntityById($id, $this->account->getAccountId());
        } else {
            $entity = $this->entityLoader->create(ObjectTypes::NOTE, $this->account->getAccountId());
        }

        $entity->setValue('owner_id', $this->user->getEntityId());
        $entity->setValue('name', $syncNote->subject);

        if (isset($syncNote->categories)) {
            $sm = $this->account->getServiceManager();
            $entityGroupingsLoader = $sm->get(GroupingLoaderFactory::class);
            $groupings = $entityGroupingsLoader->get(ObjectTypes::NOTE . "/groups/" . $this->user->getEntityId(), $this->account->getAccountId());

            foreach ($syncNote->categories as $catName) {
                // See if there is a grouping with this category name
                $group = $groupings->getByPath($catName);
                if ($group) {
                    $entity->addMultiValue("groups", $group->getGroupId());
                } else {
                    // TODO: We should dynamically add it here
                }
            }
        }


        if (
            isset($syncNote->asbody) &&
            isset($syncNote->asbody->type) &&
            isset($syncNote->asbody->data)
        ) {
            $bodyContent = stream_get_contents($syncNote->asbody->data);
            $this->log->debug("ZPUSH->EntityProvider->saveNote ");
            switch ($syncNote->asbody->type) {
                case SYNC_BODYPREFERENCE_PLAIN:
                default:
                    $entity->setValue('body', $bodyContent);
                    $entity->setValue('body_type', 'plain');
                    break;
                case SYNC_BODYPREFERENCE_HTML:
                    $entity->setValue('body', $bodyContent);
                    $entity->setValue('body_type', 'html');
                    break;
                case SYNC_BODYPREFERENCE_RTF:
                    break;
                case SYNC_BODYPREFERENCE_MIME:
                    break;
            }
        } else {
            $this->log->debug("ZPUSH->EntityProvider->saveNote either type or data are not set. Setting to empty body");
            $entity->setValue('body', $syncNote->asbody);
            $entity->setValue('body_type', 'plain');
        }

        /*
         * If this is coming from iOS, it may not have provided a subject
         * because iOS notes do not have any kind of name or title so we take
         * the first line of the body that is not empty and strip all tags
         */
        if (!isset($syncNote->subject) || empty($syncNote->subject) && strlen($entity->getValue('body'))) {
            $body = $entity->getValue("body");
            $firstLine = $this->getFirstPlainLineOfText($body);

            // Set untitled if we were unable to get the newline
            if (!$firstLine) {
                $firstLine = "Untitled";
            }

            // Set the name of the new to the first line
            $entity->setValue("name", $firstLine);
        }

        $this->log->info("ZPUSH->EntityProvider->saveNote: returning " . $entity->getEntityId());

        return $this->entityLoader->save($entity, $this->user);
    }

    /**
     * Save a task from a syncObject
     *
     * @param string $id The unique id of the entity to save
     * @param SyncTask $syncTask The data to save
     * @return string id on success, null on failure
     * @throws RuntimeException If there is a problem saving
     */
    private function saveTask($id, SyncTask $syncTask)
    {
        // Either load or create the entity
        $entity = null;
        if ($id) {
            $entity = $this->entityLoader->getEntityById($id, $this->account->getAccountId());
        } else {
            $entity = $this->entityLoader->create(ObjectTypes::TASK, $this->account->getAccountId());
        }

        $entity->setValue('owner_id', $this->user->getEntityId());
        $entity->setValue('name', $syncTask->subject);
        $entity->setValue('notes', $syncTask->body);
        if ($syncTask->startdate) {
            $entity->setValue('start_date', date("Y-m-d", $syncTask->startdate));
        }
        if ($syncTask->duedate) {
            $entity->setValue('deadline', date("Y-m-d", $syncTask->duedate));
        }

        $this->log->info("ZPUSH->EntityProvider->saveTask: returning " . $entity->getEntityId());

        return $this->entityLoader->save($entity, $this->user);
    }

    /**
     * Save a calendar_event from a syncObject
     *
     * @param string $id The unique id of the entity to save
     * @param SyncAppointment $syncAppointment The data to save
     * @param int $calendarId The calendar to save to
     * @return string|bool id on success, false on failure
     * @throws RuntimeException If there is a problem saving
     */
    private function saveAppointment($id, SyncAppointment $syncAppointment, $calendarId)
    {
        // Either load or create the entity
        $entity = null;
        if ($id) {
            $entity = $this->entityLoader->getEntityById($id, $this->account->getAccountId());
        } else {
            $entity = $this->entityLoader->create(ObjectTypes::CALENDAR_EVENT, $this->account->getAccountId());
        }

        // Get timezone info
        $tzData = false;
        if (isset($syncAppointment->timezone)) {
            $tzData = $this->getTZFromSyncBlob(base64_decode($syncAppointment->timezone));
            $tz = $tzData['tzname'];
        }

        //calculate duration because without it some webaccess views are broken. duration is in min
        $localstart = $this->getLocaltimeByTZ($syncAppointment->starttime, $tz);
        $localend = $this->getLocaltimeByTZ($syncAppointment->endtime, $tz);

        /*
         * nokia sends an yearly event with 0 mins duration but as all day event,
         * so make it end next day
         */
        if (
            $syncAppointment->starttime == $syncAppointment->endtime
            && isset($syncAppointment->alldayevent)
            && $syncAppointment->alldayevent
        ) {
            $duration = 1440;
            $syncAppointment->endtime = $syncAppointment->starttime + 24 * 60 * 60;
            $localend = $localstart + 24 * 60 * 60;
        }

        $entity->setValue("name", $syncAppointment->subject);
        $entity->setValue("location", $syncAppointment->location);
        $entity->setValue("notes", $syncAppointment->body);
        $entity->setValue("sharing", 1);
        $entity->setValue("owner_id", $this->user->getEntityId());
        $entity->setValue("all_day", ($syncAppointment->alldayevent) ? 't' : 'f');
        $entity->setValue('calendar', $calendarId);
        if ($syncAppointment->starttime) {
            $entity->setValue("ts_start", $syncAppointment->starttime);
        }
        if ($syncAppointment->endtime) {
            $entity->setValue("ts_end", $syncAppointment->endtime);
        }

        // 1 = private, 2 = public
        //$values['user_status']        = ($appointment->busystatus == 2) ? 1 : 3;
        //debugLog("\tbusystatus=".$appointment->busystatus);
        //$values['uid']            = $appointment->uid;
        //$values['organizername']  = $appointment->organizername;
        //$values['organizeremail'] = $appointment->organizeremail;
        //$values['sensitivity']    = $appointment->sensitivity;
        //$values['user_status']    = $appointment->busystatus;

        // Fix dates if all day. Apple will push to midnight of the next day...
        if ($syncAppointment->alldayevent) {
            if (date("g:i A", $syncAppointment->endtime) == "12:00 AM") {
                $entity->setValue("ts_end", date("Y-m-d", strtotime("-1 day", $syncAppointment->endtime)) . " 11:59 PM");
            }
        }

        if (isset($syncAppointment->recurrence)) {
            $rp = $entity->getRecurrencePattern();

            // Add recurrence
            if (!$rp) {
                $rp = new RecurrencePattern($this->account->getAccountId());
            }

            $rp->setRecurType(RecurrencePattern::RECUR_DAILY);

            // Set the interval of the recurrence
            if (isset($syncAppointment->recurrence->interval)) {
                $rp->setInterval($syncAppointment->recurrence->interval);
            } else {
                $rp->setInterval(1);
            }

            // Set start date
            $startDate = new \DateTime();
            if ($localstart) {
                $startDate->setTimestamp($localstart);
            }
            $rp->setDateStart($startDate);

            // Set end date
            if ($syncAppointment->recurrence->until) {
                $endDate = new \DateTime(date("Y-m-d", $syncAppointment->recurrence->until));
                $rp->setDateEnd($endDate);
            }

            switch ($syncAppointment->recurrence->type) {
                    // Daily
                case 0:
                    $rp->setRecurType(RecurrencePattern::RECUR_DAILY);
                    break;

                    // Weekly
                case 1:
                    $rp->setRecurType(RecurrencePattern::RECUR_WEEKLY);
                    $this->setRecurDayOfWeekMask($rp, $syncAppointment->recurrence->dayofweek);
                    break;

                    // Monthly
                case 2:
                    $rp->setRecurType(Netric\Entity\Recurrence\RecurrencePattern::RECUR_MONTHLY);
                    $rp->setDayOfMonth($syncAppointment->recurrence->dayofmonth);
                    break;

                    // Monthly(nth)
                case 3:
                    $rp->setRecurType(Netric\Entity\Recurrence\RecurrencePattern::RECUR_MONTHNTH);
                    $rp->setInstance($syncAppointment->recurrence->weekofmonth);
                    $this->setRecurDayOfWeekMask($rp, $syncAppointment->recurrence->dayofweek);
                    break;

                    // Yearly
                case 5:
                    $rp->setRecurType(Netric\Entity\Recurrence\RecurrencePattern::RECUR_YEARLY);
                    $this->setRecurDayOfWeekMask($rp, $syncAppointment->recurrence->dayofweek);
                    $rp->setMonthOfYear($syncAppointment->recurrence->monthofyear);
                    break;

                    // YearlyNth
                case 6:
                    $rp->setRecurType(Netric\Entity\Recurrence\RecurrencePattern::RECUR_YEARNTH);
                    $this->setRecurDayOfWeekMask($rp, $syncAppointment->recurrence->dayofweek);
                    break;

                    // Not supported
                default:
                    return null;
            }

            // TODO: Process exceptions. The PDA will send all exceptions for this recurring item.
            /*
            if(isset($appointment->exceptions))
            {
                foreach($appointment->exceptions as $exception)
                {
                    // we always need the base date
                    if(!isset($exception->exceptionstarttime))
                        continue;

                    if(isset($exception->deleted) && $exception->deleted)
                    {
                        // Delete exception
                        if(!isset($recur["deleted_occurences"]))
                            $recur["deleted_occurences"] = array();

                        array_push($recur["deleted_occurences"], $this->_getDayStartOfTimestamp($exception->exceptionstarttime));
                    }
                    else
                    {
                        // Change exception
                        $mapiexception = array("basedate" => $this->_getDayStartOfTimestamp($exception->exceptionstarttime));

                        if(isset($exception->starttime))
                            $mapiexception["start"] = $this->_getLocaltimeByTZ($exception->starttime, $tz);

                        if(isset($exception->endtime))
                            $mapiexception["end"] = $this->_getLocaltimeByTZ($exception->endtime, $tz);

                        if(isset($exception->subject))
                            $mapiexception["subject"] = u2w($exception->subject);

                        if(isset($exception->location))
                            $mapiexception["location"] = u2w($exception->location);

                        if(isset($exception->busystatus))
                            $mapiexception["busystatus"] = $exception->busystatus;

                        if(isset($exception->reminder))
                        {
                            $mapiexception["reminder_set"] = 1;
                            $mapiexception["remind_before"] = $exception->reminder;
                        }

                        if(isset($exception->alldayevent))
                            $mapiexception["alldayevent"] = $exception->alldayevent;

                        if(!isset($recur["changed_occurences"]))
                            $recur["changed_occurences"] = array();

                        array_push($recur["changed_occurences"], $mapiexception);
                    }
                }
            }
             */

            //debugLog("setAppointment recurrance - ".var_export($appointment->recurrence, true));

            $entity->setRecurrencePattern($rp);
        }

        $this->log->info("ZPUSH->EntityProvider->saveAppointment: returning " . $entity->getEntityId());

        return $this->entityLoader->save($entity, $this->user);
    }

    /**
     * Set recurrence pattern days of week from a bitmask
     *
     * @param RecurrencePattern $rp
     * @param int $mask
     */
    private function setRecurDayOfWeekMask(RecurrencePattern $rp, $mask)
    {
        $rp->setDayOfWeek(
            RecurrencePattern::WEEKDAY_SUNDAY,
            ($mask & RecurrencePattern::WEEKDAY_SUNDAY)
        );

        $rp->setDayOfWeek(
            RecurrencePattern::WEEKDAY_MONDAY,
            ($mask & RecurrencePattern::WEEKDAY_MONDAY)
        );

        $rp->setDayOfWeek(
            RecurrencePattern::WEEKDAY_TUESDAY,
            ($mask & RecurrencePattern::WEEKDAY_TUESDAY)
        );

        $rp->setDayOfWeek(
            RecurrencePattern::WEEKDAY_WEDNESDAY,
            ($mask & RecurrencePattern::WEEKDAY_WEDNESDAY)
        );

        $rp->setDayOfWeek(
            RecurrencePattern::WEEKDAY_THURSDAY,
            ($mask & RecurrencePattern::WEEKDAY_THURSDAY)
        );

        $rp->setDayOfWeek(
            RecurrencePattern::WEEKDAY_FRIDAY,
            ($mask & RecurrencePattern::WEEKDAY_FRIDAY)
        );

        $rp->setDayOfWeek(
            RecurrencePattern::WEEKDAY_SATURDAY,
            ($mask & RecurrencePattern::WEEKDAY_SATURDAY)
        );
    }

    /**
     * Save an email_message from a syncObject
     *
     * @param string $id The unique id of the entity to save
     * @param SyncMail $syncMail The data to save
     * @param int $mailboxId The id of the mailbox to save to
     * @return string|bool id on success, false on failure
     * @throws RuntimeException If there is a problem saving
     */
    private function saveEmail($id, SyncMail $syncMail, $mailboxId)
    {
        // Either load or create the entity
        $entity = null;
        if ($id) {
            $entity = $this->entityLoader->getEntityById($id, $this->account->getAccountId());
        } else {
            $entity = $this->entityLoader->create(ObjectTypes::EMAIL_MESSAGE, $this->account->getAccountId());
        }

        $entity->setValue('subject', $syncMail->subject);
        $entity->setValue('to', $syncMail->to);
        $entity->setValue("owner_id", $this->user->getEntityId());
        $entity->setValue("flag_seen", ($syncMail->read) ? true : false);

        if ($mailboxId) {
            $entity->setValue("mailbox_id", $mailboxId);
        }

        // TODO: save other fields here. Not sure there's much of a need, but just in case. - Sky
        return $this->entityLoader->save($entity, $this->user);
    }

    // Timezone Helpers
    // ====================================================================================

    /**
     * Returns an GMT timezone array
     *
     * @return array
     */
    private function getGMTTZ()
    {
        $tz = [
            "bias" => 0,
            "tzname" => "",
            "dstendyear" => 0,
            "dstendmonth" => 10,
            "dstendday" => 0,
            "dstendweek" => 5,
            "dstendhour" => 2,
            "dstendminute" => 0,
            "dstendsecond" => 0,
            "dstendmillis" => 0,
            "stdbias" => 0,
            "tznamedst" => "",
            "dststartyear" => 0,
            "dststartmonth" => 3,
            "dststartday" => 0,
            "dststartweek" => 5,
            "dststarthour" => 1,
            "dststartminute" => 0,
            "dststartsecond" => 0,
            "dststartmillis" => 0,
            "dstbias" => -60
        ];

        return $tz;
    }

    /**
     * Unpack timezone info from Sync
     *
     * @param string    $data
     *
     * @access private
     * @return array
     */
    private function getTZFromSyncBlob($data)
    {
        $tz = unpack("lbias/a64tzname/vdstendyear/vdstendmonth/vdstendday/vdstendweek/vdstendhour/vdstendminute/vdstendsecond/vdstendmillis/" .
            "lstdbias/a64tznamedst/vdststartyear/vdststartmonth/vdststartday/vdststartweek/vdststarthour/vdststartminute/vdststartsecond/vdststartmillis/" .
            "ldstbias", $data);

        // Make the structure compatible with class.recurrence.php
        $tz["timezone"] = $tz["bias"];
        $tz["timezonedst"] = $tz["dstbias"];

        // If not set, then use the users timezone
        if (!$tz['tzname'] && $this->user->timezoneName) {
            $tz['tzname'] = $this->user->timezoneName;
        }

        return $tz;
    }


    /**
     * Pack timezone info for Sync
     *
     * @param array     $tz
     *
     * @access private
     * @return string
     */
    private function getSyncBlobFromTZ($tz)
    {
        // set the correct TZ name (done using the Bias)
        if (!isset($tz["tzname"]) || !$tz["tzname"] || !isset($tz["tznamedst"]) || !$tz["tznamedst"]) {
            $tz = TimezoneUtil::FillTZNames($tz);
        }

        $packed = pack(
            "la64vvvvvvvv" . "la64vvvvvvvv" . "l",
            $tz["bias"],
            $tz["tzname"],
            0,
            $tz["dstendmonth"],
            $tz["dstendday"],
            $tz["dstendweek"],
            $tz["dstendhour"],
            $tz["dstendminute"],
            $tz["dstendsecond"],
            $tz["dstendmillis"],
            $tz["stdbias"],
            $tz["tznamedst"],
            0,
            $tz["dststartmonth"],
            $tz["dststartday"],
            $tz["dststartweek"],
            $tz["dststarthour"],
            $tz["dststartminute"],
            $tz["dststartsecond"],
            $tz["dststartmillis"],
            $tz["dstbias"]
        );

        return $packed;
    }

    /**
     * Returns the local time for the given GMT time, taking account of the given timezone
     *
     * @param long      $gmttime
     * @param array     $tz
     *
     * @access private
     * @return long
     */
    private function getLocaltimeByTZ($gmttime, $tz)
    {
        if (!isset($tz) || !is_array($tz)) {
            return $gmttime;
        }

        if ($this->isDST($gmttime - $tz["bias"] * 60, $tz)) { // may bug around the switch time because it may have to be 'gmttime - bias - dstbias'
            return $gmttime - $tz["bias"] * 60 - $tz["dstbias"] * 60;
        } else {
            return $gmttime - $tz["bias"] * 60;
        }
    }

    /**
     * Returns TRUE if it is the summer and therefore DST is in effect
     *
     * @param long      $localtime
     * @param array     $tz
     *
     * @access private
     * @return boolean
     */
    private function isDST($localtime, $tz)
    {
        if (
            !isset($tz) || !is_array($tz) ||
            !isset($tz["dstbias"]) || $tz["dstbias"] == 0 ||
            !isset($tz["dststartmonth"]) || $tz["dststartmonth"] == 0 ||
            !isset($tz["dstendmonth"]) || $tz["dstendmonth"] == 0
        ) {
            return false;
        }

        $year = gmdate("Y", $localtime);
        $start = $this->getTimestampOfWeek($year, $tz["dststartmonth"], $tz["dststartweek"], $tz["dststartday"], $tz["dststarthour"], $tz["dststartminute"], $tz["dststartsecond"]);
        $end = $this->getTimestampOfWeek($year, $tz["dstendmonth"], $tz["dstendweek"], $tz["dstendday"], $tz["dstendhour"], $tz["dstendminute"], $tz["dstendsecond"]);

        if ($start < $end) {
            // northern hemisphere (july = dst)
            if ($localtime >= $start && $localtime < $end) {
                $dst = true;
            } else {
                $dst = false;
            }
        } else {
            // southern hemisphere (january = dst)
            if ($localtime >= $end && $localtime < $start) {
                $dst = false;
            } else {
                $dst = true;
            }
        }

        return $dst;
    }

    /**
     * Returns the local timestamp for the $week'th $wday of $month in $year at $hour:$minute:$second
     *
     * @param int       $year
     * @param int       $month
     * @param int       $week
     * @param int       $wday
     * @param int       $hour
     * @param int       $minute
     * @param int       $second
     *
     * @access private
     * @return long
     */
    private function getTimestampOfWeek($year, $month, $week, $wday, $hour, $minute, $second)
    {
        if ($month == 0) {
            return;
        }

        $date = gmmktime($hour, $minute, $second, $month, 1, $year);

        // Find first day in month which matches day of the week
        while (1) {
            $wdaynow = gmdate("w", $date);
            if ($wdaynow == $wday) {
                break;
            }
            $date += 24 * 60 * 60;
        }

        // Forward $week weeks (may 'overflow' into the next month)
        $date = $date + $week * (24 * 60 * 60 * 7);

        // Reverse 'overflow'. Eg week '10' will always be the last week of the month in which the
        // specified weekday exists
        while (1) {
            $monthnow = gmdate("n", $date); // gmdate returns 1-12
            if ($monthnow > $month) {
                $date = $date - (24 * 7 * 60 * 60);
            } else {
                break;
            }
        }

        return $date;
    }

    /**
     * Normalize the given timestamp to the start of the day
     *
     * @param long      $timestamp
     *
     * @access private
     * @return long
     */
    private function getDayStartOfTimestamp($timestamp)
    {
        return $timestamp - ($timestamp % (60 * 60 * 24));
    }

    /**
     * A folderid in z-push can represent any type entity folder, so we pack the type with the id
     *
     * @param int $type The type of folder we are representing
     * @param string $id The unique id of the folder for the given type
     * @return string {typeid}:{id}
     */
    private function packFolderId($type, $id)
    {
        return $type . ":" . $id;
    }

    /**
     * A folderid in z-push can represent any type entity folder, so we unpack the type and id

     * @param string $folderid Encoded folder id in form {typeid}:{id}
     * @return array('type'=>type id from self::FOLDER_TYPE_*, 'id'=>unique folder id or null if none)
     */
    public function unpackFolderId($folderid)
    {
        $parts = explode(':', $folderid);
        return [
            'type' => $parts[0],
            'id' => (isset($parts[1])) ? $parts[1] : null
        ];
    }

    /**
     * Get the first plain line of text from a blob of text
     *
     * For example:
     *  "my text\n With a body"
     * Would return:
     *  "my text"
     *
     * @param string $text
     * @return string
     */
    private function getFirstPlainLineOfText($text)
    {
        $firstLine = "";
        $plainText = strip_tags($text);
        for ($i = 0; $i < strlen($plainText); $i++) {
            // If we have encountered a new line and previously set data in $first line then break
            if ($plainText[$i] === '\n' && strlen($firstLine)) {
                break;
            }

            if ($plainText[$i] != '\r' && $plainText[$i] != ' ') {
                $firstLine .= $plainText[$i];
            }
        }

        return $firstLine;
    }
}
