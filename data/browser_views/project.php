<?php

/**
 * Return browser views for entity of object type 'project'
 */

namespace data\browser_views;

use Netric\Entity\ObjType\UserEntity;
use Netric\EntityQuery\Where;
use Netric\EntityDefinition\ObjectTypes;

return [
    'obj_type' => ObjectTypes::PROJECT,
    'views' => [
        'my_open_projects' => [        
            'name' => 'My Open Projects',
            'description' => '',
            'default' => true,
            'filter_fields' => ['priority'],
            'conditions' => [
                'members' => [
                    'blogic' => Where::COMBINED_BY_AND,
                    'field_name' => 'members',
                    'operator' => Where::OPERATOR_EQUAL_TO,
                    'value' => UserEntity::USER_CURRENT,
                ],
                'completed' => [
                    'blogic' => Where::COMBINED_BY_AND,
                    'field_name' => 'date_completed',
                    'operator' => Where::OPERATOR_EQUAL_TO,
                    'value' => ''
                ],
            ],
            'order_by' => [
                'name' => [
                    'field_name' => 'name',
                    'direction' => 'asc',
                ],
            ],
            'table_columns' => [
                'name', 'priority', 'date_started', 'date_deadline', 'date_completed'
            ],
        ],
    
        'all_projects' => [        
            'name' => 'All Projects',
            'description' => '',
            'default' => false,
            'filter_fields' => ['priority', 'completed'],
            'order_by' => [
                'name' => [
                    'field_name' => 'name',
                    'direction' => 'asc',
                ]
            ],
            'table_columns' => [
                'name', 'priority', 'date_started', 'date_deadline', 'date_completed'
            ]
        ],
    
        'my_closed_projects' => [            
            'name' => 'My Closed Projects',
            'description' => '',
            'default' => false,
            'filter_fields' => ['priority'],
            'conditions' => [
                'members' => [
                    'blogic' => Where::COMBINED_BY_AND,
                    'field_name' => 'members',
                    'operator' => Where::OPERATOR_EQUAL_TO,
                    'value' => UserEntity::USER_CURRENT,
                ],
                'completed' => [
                    'blogic' => Where::COMBINED_BY_AND,
                    'field_name' => 'date_completed',
                    'operator' => Where::OPERATOR_NOT_EQUAL_TO,
                    'value' => ''
                ],
            ],
            'order_by' => [
                'name' => [
                    'field_name' => 'name',
                    'direction' => 'asc',
                ],
            ],
            'table_columns' => [
                'name', 'priority', 'date_started', 'date_deadline',
                'date_completed'
            ]
        ],
    
        'all_open_projects' => [        
            'name' => 'All Open Projects',
            'description' => '',
            'default' => false,
            'conditions' => [
                'completed' => [
                    'blogic' => Where::COMBINED_BY_AND,
                    'field_name' => 'date_completed',
                    'operator' => Where::OPERATOR_EQUAL_TO,
                    'value' => ''
                ],
            ],
            'order_by' => [
                'name' => [
                    'field_name' => 'name',
                    'direction' => 'asc',
                ],
            ],
            'table_columns' => [
                'name', 'priority', 'date_started', 'date_deadline', 'date_completed'
            ]
        ],
    
        'ongoing_projects' => [        
            'name' => 'Ongoing Projects (no deadline)',
            'description' => '',
            'default' => false,
            'filter_fields' => ['priority'],
            'conditions' => [
                'deadline' => [
                    'blogic' => Where::COMBINED_BY_AND,
                    'field_name' => 'date_deadline',
                    'operator' => Where::OPERATOR_EQUAL_TO,
                    'value' => ''
                ],
            ],
            'order_by' => [
                'name' => [
                    'field_name' => 'name',
                    'direction' => 'asc',
                ],
            ],
            'table_columns' => ['name', 'priority', 'date_started', 'date_deadline', 'date_completed']
        ],
    
        'late_projects' => [        
            'name' => 'Late Projects',
            'description' => '',
            'default' => false,
            'filter_fields' => ['priority', 'completed'],
            'conditions' => [
                'deadline' => [
                    'blogic' => Where::COMBINED_BY_AND,
                    'field_name' => 'date_deadline',
                    'operator' => Where::OPERATOR_LESS_THAN,
                    'value' => 'now'
                ],
            ],
            'order_by' => [
                'name' => [
                    'field_name' => 'name',
                    'direction' => 'asc',
                ],
            ],
            'table_columns' => ['name', 'priority', 'date_started', 'date_deadline', 'date_completed']
        ],
    ]
];
