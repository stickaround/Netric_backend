<?php

namespace Netric\Permissions;

use Netric\Entity\ObjType\UserEntity;
use Netric\Entity\EntityInterface;
use Netric\Permissions\Dacl\Entry as DaclEntry;
use Ramsey\Uuid\Uuid;

/**
 * Discretionary access control list
 */
class Dacl
{
    /**
     * Saved DACLs will all have a unique id
     *
     * @var int
     */
    private $id = null;

    /**
     * Each DACL may have a unique name/key to access it by
     *
     * @var string
     */
    private $name = null;

    /**
     * Associative array with either group or user associated with an permission
     *
     * @var DaclEntry[]
     */
    private $entries = [];

    /**
     * Flag used as an override that allows everyone access by default
     *
     * @var bool
     */
    private $everyoneAllowed = false;

    /**
     * The default permission to check if none is supplied
     */
    const PERM_DEFAULT = "View";

    /**
     * Permission entry constants
     */
    const PERM_FULL = "Full Control";
    const PERM_VIEW = "View";
    const PERM_EDIT = "Edit";
    const PERM_DELETE = "Delete";

    /**
     * Default entries to make
     *
     * @var array
     */
    private $defaultEntries = [
        self::PERM_VIEW,
        self::PERM_EDIT,
        self::PERM_DELETE
    ];

    /**
     * Class constructor
     *
     * @param array $data Optional initialization data
     */
    public function __construct(array $data = null)
    {
        if ($data) {
            $this->fromArray($data);
        }

        // Create default entries
        foreach ($this->defaultEntries as $entName) {
            if (!isset($this->entries[$entName])) {
                $this->entries[$entName] = new DaclEntry(['name' => $entName]);
            }
        }
    }

    /**
     * Load definition of this array from data array
     *
     * @var array $data Associative array with 'permissions' and 'entries'
     * @return bool True on success, false on failure
     */
    public function fromArray($data)
    {
        if (!is_array($data)) {
            return false;
        }

        if (isset($data['name'])) {
            $this->name = $data['name'];
        }

        if (isset($data['id'])) {
            $this->id = $data['id'];
        }

        if (isset($data['entries']) && is_array($data['entries'])) {
            $this->setEntries($data['entries']);
        }
    }

    /**
     * Return this list as an array
     *
     * @return array Associative array representing this DACL
     */
    public function toArray()
    {
        $ret = [
            'name' => $this->name,
            'entries' => []
        ];

        foreach ($this->entries as $key => $entry) {
            $ret['entries'][$key] = $entry->toArray();
        }

        return $ret;
    }

    /**
     * Clear entries
     */
    public function clearEntries()
    {
        $this->entries = [];
    }

    /**
     * Set local entries from array
     *
     * @param array $entries Array of Entries to load from associative data
     */
    private function setEntries(array $entries)
    {
        foreach ($entries as $pname => $entryData) {
            $entry = new DaclEntry();
            $entry->fromArray($entryData);

            $permissionName = ($entryData['name']) ? $entryData['name'] : $pname;
            $this->entries[$permissionName] = $entry;
        }
    }

    /**
     * Get array of users mentioned in the entries
     *
     * @return array(array('id','name')) of users
     */
    public function getUsers()
    {
        $uids = [];

        // Get distinct list of users
        foreach ($this->entries as $ent) {
            foreach ($ent->users as $userId) {
                if (!in_array($userId, $uids)) {
                    $uids[] = $userId;
                }
            }
        }

        return $uids;
    }

    /**
     * Get array of groups mentioned in the entries
     *
     * @return array(array('id','name')) of users
     */
    public function getGroups()
    {
        $gids = [];

        // Get distinct list of users
        foreach ($this->entries as $ent) {
            foreach ($ent->groups as $groupId) {
                if (!in_array($groupId, $gids)) {
                    $gids[] = $groupId;
                }
            }
        }

        return $gids;
    }

    /**
     * Grant access to a user to a specific permission
     *
     * @param int $userId The user id to grant access to
     * @param string $permission The permssion to grant access to
     */
    public function allowUser($userId, $permission = self::PERM_FULL)
    {
        if (self::PERM_FULL == $permission) {
            foreach ($this->entries as $pname => $ent) {
                $this->allowUser($userId, $pname);
            }
        } else {
            // Create entry if it does not exist
            if (!isset($this->entries[$permission])) {
                $this->entries[$permission] = new DaclEntry();
            }

            // Add the user
            if (!in_array($userId, $this->entries[$permission]->users)) {
                $this->entries[$permission]->users[] = $userId;
            }
        }
    }

    /**
     * Grant access to a group to a specific permission
     *
     * @param int $gid The group id to grant access to
     * @param string $permission The permssion to grant access to
     */
    public function allowGroup($gid, $permission = self::PERM_FULL)
    {
        if (self::PERM_FULL == $permission) {
            foreach ($this->entries as $pname => $ent) {
                $this->allowGroup($gid, $pname);
            }
        } else {
            // Add specific permission
            if (!isset($this->entries[$permission])) {
                $this->entries[$permission] = new DaclEntry();
            }

            // Grant group access
            if (!in_array($gid, $this->entries[$permission]->groups)) {
                $this->entries[$permission]->groups[] = $gid;
            }
        }
    }

    /**
     * Used to make a generic DACL that always allows everyone by default
     *
     * Note: This is a read-only runtime property and will not be saved.
     *
     * @return void
     */
    public function allowEveryone(): void
    {
        $this->everyoneAllowed = true;
    }

    /**
     * Remove a user from a specific permission
     *
     * @param int $userId The user id to remove
     * @param string $permission The permission to clear
     */
    public function denyUser($userId, $permission = self::PERM_FULL)
    {
        $entries = [];

        if (self::PERM_FULL == $permission) {
            $entries = $this->entries;
        } elseif (isset($this->entries[$permission])) {
            $entries[] = $this->entries[$permission];
        }

        foreach ($entries as $entry) {
            $entry->removeUser($userId);
        }
    }

    /**
     * Remove a group from a specific permission entry
     *
     * @param int $gid The group id to remove
     * @param string $permission The permission to clear
     */
    public function denyGroup($gid, $permission = "Full Control")
    {
        $entries = [];

        if (self::PERM_FULL == $permission) {
            $entries = $this->entries;
        } elseif (isset($this->entries[$permission])) {
            $entries[] = $this->entries[$permission];
        }

        foreach ($entries as $entry) {
            $entry->removeGroup($gid);
        }
    }

    /**
     * Check if a user has access to a permission either directly or through group membership
     *
     * @param UserEntity $user The user to check for access
     * @param string $permission The permission to check against. Defaults to 'Full Control'
     * @param EntityInterface $entity Optional. If entity is provided, then we can check if the $user is assigned as owner/creator/user of $entity
     * @return bool true if allowed, false if not allowed
     */
    public function isAllowed(UserEntity $user, $permission = self::PERM_FULL, $entity = null)
    {
        $userGuid = $user->getEntityId();

        // First check to see if this is a wide-open DACL
        if ($this->everyoneAllowed) {
            return true;
        }

        /*
         * If $entity is provided and if the $user is the owner/creator of $entity or if $user was assigned to $entity
         * Then no need to check for the dacl entries
         */
        if ($entity && ($entity->getValue("owner_id") == $userGuid || $entity->getOwnerId() == $userGuid || $user->getEntityId() == $entity->getEntityId())) {
            return true;
        }

        // First check to see if the user has full control
        if (self::PERM_FULL == $permission) {
            foreach ($this->entries as $pname => $entry) {
                if (!$this->isAllowed($user, $pname, $entity)) {
                    return false;
                }
            }

            /*
             * We got through all the above without returning false, must mean we have
             * access, unless of course there were no entries at all,
             */
            return (count($this->entries) > 0) ? true : false;
        }

        if (isset($this->entries[$permission])) {
            // Test users
            foreach ($this->entries[$permission]->users as $uid) {
                if ($uid == $user->getEntityId()) {
                    return true;
                }
            }

            // Test groups
            $groups = $user->getGroups();
            foreach ($this->entries[$permission]->groups as $gid) {
                if (in_array($gid, $groups)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the permissions for the user provided
     *
     * @param UserEntity $user The user to check for access
     * @param EntityInterface $entity Optional. If entity is provided, then we can check if the $user is assigned as owner/creator/user of $entity
     * @return object Returns the permissions (view, edit, delete) for the user
     */
    public function getUserPermissions(UserEntity $user, $entity = null)
    {
        return ([
            'view' => $this->isAllowed($user, Dacl::PERM_VIEW, $entity),
            'edit' => $this->isAllowed($user, Dacl::PERM_EDIT, $entity),
            'delete' => $this->isAllowed($user, Dacl::PERM_DELETE, $entity)
        ]);
    }

    /**
     * Check if a specific group has permissions
     *
     * @param int $groupId
     * @param string $permission
     * @return bool true if allowed, false if no permission
     */
    public function groupIsAllowed($groupId, $permission = self::PERM_FULL)
    {
        if (self::PERM_FULL == $permission) {
            foreach ($this->entries as $pname => $entry) {
                if (!$this->groupIsAllowed($groupId, $pname)) {
                    return false;
                }
            }
        } else {
            // Test through each group to see if permission is granted to the given group
            foreach ($this->entries[$permission]->groups as $gid) {
                if ($gid === $groupId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Verifies if we have valid uuids in the dacl data
     * 
     * @return bool true if dacl data has valid, otherwise false
     */
    public function verifyDaclData(): bool {
        $uuids = array_merge($this->getGroups(), $this->getUsers());
        
        foreach($uuids as $uuid) {
            // If uuid is not empty and not valid uuid, this means that we have a bad dacl data
            if (!empty($uuid) && !Uuid::isValid($uuid)) {
                return false;
            }
        }

        return true;
    }
}
