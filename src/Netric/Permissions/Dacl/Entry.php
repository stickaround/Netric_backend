<?php

/**
 * Access control list entry for a permission
 *
 * This will represent a permission like "View" and contains
 * which groups and users have access to that permission
 *
 * @author Sky Stebnicki <sky.stebnicki@aereus.com>
 * @copyright 2016 Aereus
 */

namespace Netric\Permissions\Dacl;

use Ramsey\Uuid\Uuid;

/**
 * ACL entry
 */
class Entry
{
    /**
     * Group IDs with access
     *
     * @var int[]
     */
    public $groups = [];

    /**
     * User IDs with access to this entry
     *
     * @var string[]
     */
    public $users = [];

    /**
     * Unique ID of this entry (if any)
     *
     * @var string
     */
    public $id = "";

    /**
     * If the entry has a parent like "Full Controll" then then ID will be here
     *
     * @var string
     */
    public $parentId = "";

    /**
     * The name of this list entry like 'Read Access'
     *
     * @var string
     */
    private $name = "";

    /**
     * Class constructor
     *
     * @param array $data Associative array that contains the initial data of the entry
     */
    public function __construct($data = [])
    {
        if (isset($data["id"])) {
            $this->id = $data["id"];
        }

        if (isset($data["name"])) {
            $this->name = $data["name"];
        }

        if (isset($data["parent"])) {
            $this->parentId = $data["parent"];
        }
    }

    /**
     * Conver the state of this entry to an array
     *
     * @return array Associative array of entry
     */
    public function toArray()
    {
        return [
            'name' => $this->name,
            'groups' => $this->groups,
            'users' => $this->users,
            'parent_id' => $this->parentId,
        ];
    }

    /**
     * Initialize entry properties from an associative array
     *
     * @param array $data Associative representation of this entry
     */
    public function fromArray(array $data)
    {
        if (isset($data['name'])) {
            $this->name = $data['name'];
        }

        if (isset($data['parent_id'])) {
            $this->parentId = $data['parent_id'];
        }

        if (isset($data['users']) && is_array($data['users'])) {
            $this->users = [];
            // Make sure the data we are importing is a valid uuid since older DACLs
            // used numbers and that can cause major problems with failing permissions
            foreach ($data['users'] as $uid) {
                if (Uuid::isValid($uid)) {
                    $this->users[] = $uid;
                }
            }
        }

        if (isset($data['groups']) && is_array($data['groups'])) {
            $this->groups = [];
            // Make sure the data we are importing is a valid uuid since older DACLs
            // used numbers and that can cause major problems with failing permissions
            foreach ($data['groups'] as $gid) {
                if (Uuid::isValid($gid)) {
                    $this->groups[] = $gid;
                }
            }
        }
    }

    /**
     * Remove a user from this entry
     *
     * @param int $userId The id of the user to remove
     */
    public function removeUser($userId)
    {
        for ($i = 0; $i < count($this->users); $i++) {
            if ($this->users[$i] == $userId) {
                array_splice($this->users, $i, 1);
                return;
            }
        }
    }

    /**
     * Remove a group from this entry
     *
     * @param int $groupId The id of the group to remove
     */
    public function removeGroup($groupId)
    {
        for ($i = 0; $i < count($this->groups); $i++) {
            if ($this->groups[$i] == $groupId) {
                array_splice($this->groups, $i, 1);
                return;
            }
        }
    }
}
