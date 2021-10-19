<?php

/**
 * Return browser views for entity of object type 'ticket_channel'
 */

namespace data\browser_views;

use Netric\Entity\ObjType\UserEntity;
use Netric\EntityDefinition\ObjectTypes;
use Netric\EntityQuery\Where;

return [
    'my_channels' => [
        'obj_type' => ObjectTypes::TICKET_CHANNEL,
        'name' => 'My Channels',
        'description' => 'Channels where I am a member',
        'default' => true,
        'conditions' => [
            'members' => [
                'blogic' => Where::COMBINED_BY_AND,
                'field_name' => 'members',
                'operator' => Where::OPERATOR_EQUAL_TO,
                'value' => UserEntity::USER_CURRENT,
            ],
        ],
        'order_by' => [
            'updated' => [
                'field_name' => 'ts_updated',
                'direction' => 'desc',
            ],
        ],
        'table_columns' => ['name']
    ],
    'all_channels' => [
        'obj_type' => ObjectTypes::TICKET_CHANNEL,
        'name' => 'All Channels',
        'description' => 'Browse all support channels',
        'default' => false,
        'order_by' => [
            'updated' => [
                'field_name' => 'ts_updated',
                'direction' => 'desc',
            ],
        ],
        'table_columns' => ['name']
    ],
];