<?php

namespace data\entity_definitions;

use Netric\EntityDefinition\Field;
use Netric\EntityDefinition\ObjectTypes;

return [
    'inherit_dacl_ref' => 'project',
    'recur_rules' => [
        "field_time_start" => "",
        "field_time_end" => "",
        "field_date_start" => "deadline",
        "field_date_end" => "deadline",
        "field_recur_id" => "recur_id"
    ],
    'fields' => [
        'name' => [
            'title' => 'Name',
            'type' => Field::TYPE_TEXT,
            'subtype' => '',
            'readonly' => false,
            'required' => true
        ],
        'notes' => [
            'title' => 'Notes',
            'type' => Field::TYPE_TEXT,
            'subtype' => '',
            'readonly' => false
        ],
        'is_closed' => [
            'title' => 'Closed',
            'type' => Field::TYPE_BOOL,
            'subtype' => '',
            'readonly' => false
        ],
        'entered_by' => [
            'title' => 'Entered By',
            'type' => Field::TYPE_TEXT,
            'subtype' => '128',
            'readonly' => true
        ],
        'cost_estimated' => [
            'title' => 'Estimated Time',
            'type' => Field::TYPE_NUMBER,
            'subtype' => 'double precision',
            'readonly' => false
        ],
        'cost_actual' => [
            'title' => 'Actual Time',
            'type' => Field::TYPE_NUMBER,
            'subtype' => 'double precision',
            'readonly' => true
        ],
        'date_entered' => [
            'title' => 'Date Entered',
            'type' => Field::TYPE_DATE,
            'subtype' => '',
            'readonly' => true,
            'default' => ["value" => "now", "on" => "create"]
        ],
        'date_completed' => [
            'title' => 'Date Completed',
            'type' => Field::TYPE_DATE,
            'subtype' => '',
            'readonly' => true,
        ],
        'deadline' => [
            'title' => 'Due Date',
            'type' => Field::TYPE_DATE,
            'subtype' => '',
            'readonly' => false
        ],
        'start_date' => [
            'title' => 'Start Date',
            'type' => Field::TYPE_DATE,
            'subtype' => '',
            'readonly' => false
        ],
        'milestone_id' => [
            'title' => 'Milestone',
            'type' => Field::TYPE_OBJECT,
            'subtype' => 'project_milestone',
            'fkey_table' => ["key" => "id", "title" => "name"]
        ],
        'depends_task_id' => [
            'title' => 'Depends On',
            'type' => Field::TYPE_OBJECT,
            'subtype' => 'task',
            'fkey_table' => ["key" => "id", "title" => "name"]

        ],
        'priority_id' => [
            'title' => 'Priority',
            'type' => Field::TYPE_GROUPING,
            'subtype' => 'object_groupings',
        ],
        'status_id' => [
            'title' => 'Status',
            'type' => Field::TYPE_GROUPING,
            'subtype' => 'object_groupings',
            'required' => true
        ],
        'project' => [
            'title' => 'Project',
            'type' => Field::TYPE_OBJECT,
            'subtype' => 'project'
        ],
        'contact_id' => [
            'title' => 'Contact',
            'type' => Field::TYPE_OBJECT,
            'subtype' => ObjectTypes::CONTACT,
        ],
        'recur_id' => [
            'title' => 'Recurrance',
            'type' => Field::TYPE_TEXT,
            'subtype' => 36,
            'readonly' => true
        ],
        'obj_reference' => [
            'title' => 'Reference',
            'type' => Field::TYPE_OBJECT,
            'subtype' => '',
            'readonly' => true,
            'system' => true,
            'is_indexed' => true,
        ],
        'type_id' => [
            'title' => 'Type',
            'type' => Field::TYPE_GROUPING,
            'subtype' => 'object_groupings',
        ],
        // Override field in default.php because don't want a default value for tasks
        // since they can be unassinged
        'owner_id' => [
            'title' => 'Assigned To',
            'type' => Field::TYPE_OBJECT,
            'subtype' => ObjectTypes::USER,
        ],
    ]
];
