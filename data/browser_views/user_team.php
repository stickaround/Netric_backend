<?php

/**
 * Return browser views for entity of object type 'user_team'
 */

namespace data\browser_views;
use Netric\EntityDefinition\ObjectTypes;

return [
    'obj_type' => ObjectTypes::USER_TEAM,
    'views' => [
        'all-teams' => [            
            'name' => 'All Teams',
            'description' => 'All Teams',
            'default' => true,
            'filter_fields' => [],
            'order_by' => [
                'name' => [
                    'field_name' => 'name',
                    'direction' => 'asc',
                ],
            ],
            'table_columns' => ['name']
        ]
    ]
];
