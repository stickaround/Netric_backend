<?php

declare(strict_types=1);

namespace data\entity_definitions;

use Netric\EntityDefinition\Field;
use Netric\Entity\ObjType\UserEntity;
use Netric\EntityDefinition\ObjectTypes;

return [
    "entity_id" => [
        'title' => "ID",
        'type' => Field::TYPE_UUID,
        'subtype' => "",
        'readonly' => true,
        'system' => true,
        'is_indexed' => true,
    ],
    "account_id" => [
        'title' => "Account ID",
        'type' => Field::TYPE_UUID,
        'subtype' => "",
        'readonly' => true,
        'system' => true,
        'is_indexed' => true,
    ],
    'associations' => [
        'title' => 'Associations',
        'type' => Field::TYPE_OBJECT_MULTI,
        'subtype' => '',
        'readonly' => true,
        'system' => true,
    ],
    'attachments' => [
        'title' => 'Attachments',
        'type' => Field::TYPE_OBJECT_MULTI,
        'subtype' => ObjectTypes::FILE,
        'readonly' => true,
        'system' => true,
    ],
    // Users that should be notified of changes to an entity
    'followers' => [
        'title' => 'Followers',
        'type' => Field::TYPE_OBJECT_MULTI,
        'subtype' => ObjectTypes::USER,
        'readonly' => true,
        'system' => true,
    ],
    // Users who have viewed an entity
    'activity' => [
        'title' => 'Activity',
        'type' => Field::TYPE_OBJECT_MULTI,
        'subtype' => ObjectTypes::ACTIVITY,
        'system' => true,
    ],
    'comments' => [
        'title' => 'Comments',
        'type' => Field::TYPE_OBJECT_MULTI,
        'subtype' => ObjectTypes::COMMENT,
        'readonly' => false,
        'system' => true,
    ],
    'num_comments' => [
        'title' => 'Num Comments',
        'type' => Field::TYPE_NUMBER,
        'subtype' => Field::TYPE_INTEGER,
        'readonly' => true,
        'system' => true,
    ],
    'commit_id' => [
        'title' => 'Commit Revision',
        'type' => Field::TYPE_NUMBER,
        'subtype' => '',
        'readonly' => true,
        'system' => true,
    ],
    'f_deleted' => [
        'title' => 'Deleted',
        'type' => Field::TYPE_BOOL,
        'subtype' => '',
        'readonly' => true,
        'system' => true,
    ],
    'owner_id' => [
        'title' => 'Assigned To',
        'type' => Field::TYPE_OBJECT,
        'subtype' => ObjectTypes::USER,
        'default' => ["value" => UserEntity::USER_CURRENT, "on" => "null"]
    ],
    'creator_id' => [
        'title' => 'Creator',
        'type' => Field::TYPE_OBJECT,
        'subtype' => ObjectTypes::USER,
        'readonly' => true,
        'default' => ["value" => UserEntity::USER_CURRENT, "on" => "null"]
    ],

    // Default is true on null for this so not every entity is marked as unseen (annoying]
    'is_seen' => [
        'title' => 'Seen',
        'type' => Field::TYPE_BOOL,
        'subtype' => '',
        'readonly' => true,
        'system' => true,
        'default' => [
            "value" => true,
            "on" => "null"
        ],
    ],

    // List of users who have seen this entity
    'seen_by' => [
        'title' => 'Seen By',
        'type' => Field::TYPE_OBJECT_MULTI,
        'subtype' => ObjectTypes::USER,
        'readonly' => true,
        'system' => true,
        'is_indexed' => true,
        'default' => ["value" => UserEntity::USER_CURRENT, "on" => "null"],
    ],

    'revision' => [
        'title' => 'Revision',
        'type' => Field::TYPE_NUMBER,
        'subtype' => '',
        'readonly' => true,
        'system' => true,
    ],

    // The full path based on parent objects
    // DEPRICATED: appears to no longer be used, but maybe we should start
    // because searches would be a lot easier in the future.
    'path' => [
        'title' => 'Path',
        'type' => Field::TYPE_TEXT,
        'subtype' => '',
        'readonly' => true,
        'system' => true,
    ],

    // Unique name in URL escaped form if object type uses it, otherwise the id
    'uname' => [
        'title' => 'Uname',
        'type' => Field::TYPE_TEXT,
        'subtype' => '256',
        'readonly' => true,
        'system' => true,
        'is_indexed' => true,
    ],
    'dacl' => [
        'title' => 'Security',
        'type' => Field::TYPE_TEXT,
        'subtype' => '',
        'readonly' => true,
        'system' => true,
    ],
    'ts_entered' => [
        'title' => 'Time Entered',
        'type' => Field::TYPE_TIMESTAMP,
        'subtype' => '',
        'readonly' => true,
        'system' => true,
        'default' => [
            "value" => "now",
            "on" => "create"
        ],
    ],
    'ts_updated' => [
        'title' => 'Time Last Changed',
        'type' => Field::TYPE_TIMESTAMP,
        'subtype' => '',
        'readonly' => true,
        'system' => true,
        'default' => [
            "value" => "now",
            "on" => "update"
        ],
    ],
    'sort_order' => [
        'title' => 'Sort Order',
        'type' => Field::TYPE_NUMBER,
        'subtype' => '',
        'readonly' => true,
        'system' => true,
        'default' => [
            "value" => "now",
            "on" => "null"
        ],
    ],
    'num_reactions' => [
        'title' => 'Num Reactions',
        'type' => Field::TYPE_NUMBER,
        'subtype' => Field::TYPE_INTEGER,
        'readonly' => true,
        'system' => true,
    ],
];
