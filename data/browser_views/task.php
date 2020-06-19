<?php

/**
 * Return browser views for entity of object type 'note'
 */

namespace data\browser_views;

use Netric\Entity\ObjType\UserEntity;
use Netric\Entity\ObjType\TaskEntity;
use Netric\EntityQuery\Where;

return array(
    'my_tasks' => array(
        'obj_type' => 'task',
        'name' => 'My Incomplete Tasks',
        'description' => 'Incomplete tasks assigned to me',
        'default' => true,
        'conditions' => array(
            'user' => array(
                'blogic' => Where::COMBINED_BY_AND,
                'field_name' => 'owner_id',
                'operator' => Where::OPERATOR_EQUAL_TO,
                'value' => UserEntity::USER_CURRENT,
            ),
            'status_id' => array(
                'blogic' => Where::COMBINED_BY_AND,
                'field_name' => 'status_id',
                'operator' => Where::OPERATOR_NOT_EQUAL_TO,
                'value' => TaskEntity::STATUS_COMPLETED
            ),
        ),
        'group_first_order_by' => true,
        'order_by' => array(
            'status_id' => array(
                'field_name' => 'status_id',
                'direction' => 'desc',
            ),
            'date' => array(
                'field_name' => 'date_entered',
                'direction' => 'desc',
            ),
            'deadline' => array(
                'field_name' => 'deadline',
                'direction' => 'asc'
            ),
        ),
        'table_columns' => array('name', 'project', 'status_id', 'deadline')
    ),

    'my_tasks_due_today' => array(
        'obj_type' => 'task',
        'name' => 'My Incomplete Tasks (due today)',
        'description' => 'Incomplete tasks assigned to me that are due today',
        'default' => false,
        'conditions' => array(
            'user' => array(
                'blogic' => Where::COMBINED_BY_AND,
                'field_name' => 'owner_id',
                'operator' => Where::OPERATOR_EQUAL_TO,
                'value' => UserEntity::USER_CURRENT,
            ),
            'status_id' => array(
                'blogic' => Where::COMBINED_BY_AND,
                'field_name' => 'status_id',
                'operator' => Where::OPERATOR_NOT_EQUAL_TO,
                'value' => TaskEntity::STATUS_COMPLETED
            ),
            'deadline' => array(
                'blogic' => Where::COMBINED_BY_AND,
                'field_name' => 'deadline',
                'operator' => Where::OPERATOR_LESS_THAN_OR_EQUAL_TO,
                'value' => 'now'
            ),
        ),
        'order_by' => array(
            'date' => array(
                'field_name' => 'date_entered',
                'direction' => 'desc',
            ),
            'deadline' => array(
                'field_name' => 'deadline',
                'direction' => 'asc'
            ),
        ),
        'table_columns' => array('name', 'project', 'status_id', 'deadline')
    ),

    'all_my_tasks' => array(
        'obj_type' => 'task',
        'name' => 'All My Tasks',
        'description' => 'All tasks assigned to me',
        'default' => false,
        'conditions' => array(
            'user' => array(
                'blogic' => Where::COMBINED_BY_AND,
                'field_name' => 'owner_id',
                'operator' => Where::OPERATOR_EQUAL_TO,
                'value' => UserEntity::USER_CURRENT,
            ),
        ),
        'order_by' => array(
            'date' => array(
                'field_name' => 'date_entered',
                'direction' => 'desc',
            ),
            'deadline' => array(
                'field_name' => 'deadline',
                'direction' => 'asc'
            ),
        ),
        'table_columns' => array('name', 'project', 'status_id', 'deadline')
    ),

    'tasks_i_have_assigned' => array(
        'obj_type' => 'task',
        'name' => 'Tasks I Have Assigned',
        'description' => 'Tasks that were created by me but assigned to someone else',
        'default' => false,
        'group_first_order_by' => true,
        'conditions' => array(
            'creator' => array(
                'blogic' => Where::COMBINED_BY_AND,
                'field_name' => 'creator_id',
                'operator' => Where::OPERATOR_EQUAL_TO,
                'value' => UserEntity::USER_CURRENT,
            ),
            'user' => array(
                'blogic' => Where::COMBINED_BY_AND,
                'field_name' => 'owner_id',
                'operator' => Where::OPERATOR_NOT_EQUAL_TO,
                'value' => UserEntity::USER_CURRENT,
            ),
            'status_id' => array(
                'blogic' => Where::COMBINED_BY_AND,
                'field_name' => 'status_id',
                'operator' => Where::OPERATOR_NOT_EQUAL_TO,
                'value' => TaskEntity::STATUS_COMPLETED
            ),
        ),
        'order_by' => array(
            'date' => array(
                'field_name' => 'date_entered',
                'direction' => 'desc',
            ),
            'deadline' => array(
                'field_name' => 'deadline',
                'direction' => 'asc'
            ),
        ),
        'table_columns' => array('name', 'project', 'status_id', 'deadline', 'owner_id')
    ),

    'all_incomplete_tasks' => array(
        'obj_type' => 'task',
        'name' => 'All Incomplete Tasks',
        'description' => 'All Tasks that have not yet been completed',
        'default' => false,
        'conditions' => array(
            'status_id' => array(
                'blogic' => Where::COMBINED_BY_AND,
                'field_name' => 'status_id',
                'operator' => Where::OPERATOR_NOT_EQUAL_TO,
                'value' => TaskEntity::STATUS_COMPLETED
            ),
        ),
        'order_by' => array(
            'status_id' => array(
                'field_name' => 'status_id',
                'direction' => 'desc',
            ),
            'date' => array(
                'field_name' => 'date_completed',
                'direction' => 'desc',
            ),
            'deadline' => array(
                'field_name' => 'deadline',
                'direction' => 'asc'
            ),
        ),
        'table_columns' => array('name', 'project', 'status_id', 'deadline', 'owner_id')
    ),

    'all_tasks' => array(
        'obj_type' => 'task',
        'name' => 'All Tasks',
        'description' => 'All Tasks',
        'default' => false,
        'order_by' => array(
            'date' => array(
                'field_name' => 'date_entered',
                'direction' => 'desc',
            ),
            'deadline' => array(
                'field_name' => 'deadline',
                'direction' => 'asc'
            ),
        ),
        'table_columns' => array('name', 'project', 'status_id', 'deadline', 'owner_id')
    ),
);
