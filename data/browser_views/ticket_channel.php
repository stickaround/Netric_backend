<?php

/**
 * Return browser views for entity of object type 'ticket_channel'
 */

namespace data\browser_views;

use Netric\Entity\ObjType\UserEntity;
use Netric\EntityDefinition\ObjectTypes;
use Netric\EntityQuery\Where;

return [
    'obj_type' => ObjectTypes::TICKET_CHANNEL,
    'views' => [
        'default' => [            
            'name' => 'All Channels',
            'description' => 'Browse all support channels',
            'default' => true,
            'filter_fields' => ['email_account_id', 'owner_id', 'creator_id'],
            'order_by' => [
                'updated' => [
                    'field_name' => 'ts_updated',
                    'direction' => 'desc',
                ],
            ],
            'table_columns' => ['name']
        ],
        'my_channels' => [
            'name' => 'My Channels',
            'description' => 'Channels where I am a member',
            'default' => false,
            'filter_fields' => ['email_account_id', 'owner_id', 'creator_id'],
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
            'name' => 'All Channels',
            'description' => 'Browse all support channels',
            'default' => false,
            'filter_fields' => ['email_account_id', 'owner_id', 'creator_id'],
            'order_by' => [
                'updated' => [
                    'field_name' => 'ts_updated',
                    'direction' => 'desc',
                ],
            ],
            'table_columns' => ['name']
        ],
    ]
];
