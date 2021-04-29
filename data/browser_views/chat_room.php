<?php

/**
 * Return browser views for entity of object type 'note'
 */

namespace data\browser_views;

use Netric\Entity\ObjType\UserEntity;
use Netric\EntityQuery\Where;

return [
    'my_rooms' => [
        'obj_type' => 'chat_room',
        'name' => 'My Rooms',
        'description' => 'Rooms where I am a member',
        'default' => true,
        'conditions' => [
            'members' => [
                'blogic' => Where::COMBINED_BY_AND,
                'field_name' => 'members',
                'operator' => Where::OPERATOR_EQUAL_TO,
                'value' => UserEntity::USER_CURRENT,
            ],
            'scope' => [
                'blogic' => Where::COMBINED_BY_AND,
                'field_name' => 'scope',
                'operator' => Where::OPERATOR_EQUAL_TO,
                'value' => 'room'
            ],
        ],
        'order_by' => [
            'updated' => [
                'field_name' => 'ts_updated',
                'direction' => 'desc',
            ],
        ],
        'table_columns' => ['subject', 'last_message_body']
    ],

    'my_direct_messages' => [
        'obj_type' => 'chat_room',
        'name' => 'Direct Messages',
        'description' => 'Direct conversations not hosted in a room',
        'default' => false,
        'conditions' => [
            'members' => [
                'blogic' => Where::COMBINED_BY_AND,
                'field_name' => 'members',
                'operator' => Where::OPERATOR_EQUAL_TO,
                'value' => UserEntity::USER_CURRENT,
            ],
            'scope' => [
                'blogic' => Where::COMBINED_BY_AND,
                'field_name' => 'scope',
                'operator' => Where::OPERATOR_EQUAL_TO,
                'value' => 'direct'
            ],
        ],
        'order_by' => [
            'updated' => [
                'field_name' => 'ts_updated',
                'direction' => 'desc',
            ],
        ],
        'table_columns' => ['subject', 'last_message_body']
    ],

    'all_rooms' => [
        'obj_type' => 'chat_room',
        'name' => 'All Rooms',
        'description' => 'Browse all chat rooms',
        'default' => false,
        'conditions' => [
            'scope' => [
                'blogic' => Where::COMBINED_BY_AND,
                'field_name' => 'scope',
                'operator' => Where::OPERATOR_NOT_EQUAL_TO,
                'value' => 'direct'
            ],
        ],
        'order_by' => [
            'updated' => [
                'field_name' => 'ts_updated',
                'direction' => 'desc',
            ],
        ],
        'table_columns' => ['subject', 'last_message_body']
    ],
];
