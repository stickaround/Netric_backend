<?php
namespace data\entity_definitions;
use Netric\EntityDefinition\Field;

return array(
    'fields' => array(
        'name' => array('title'=>'Name', 'type'=>'text', 'subtype'=>'512', 'readonly'=>false),
        'location' => array('title'=>'Location', 'type'=>'text', 'subtype'=>'512', 'readonly'=>false),
        'notes' => array('title'=>'Notes', 'type'=>'text', 'subtype'=>'', 'readonly'=>false),
        'f_closed' => array('title'=>'Closed/Converted', 'type'=>'bool', 'subtype'=>'', 'readonly'=>false),
        'user_id' => array('title'=>'Owner',
            'type'=>Field::TYPE_OBJECT,
            'subtype'=>'user',
            'default'=>array("value"=>"-3", "on"=>"null")
        ),
        'event_id' => array(
            'title'=>'Event',
            'type'=>Field::TYPE_OBJECT,
            'subtype'=>'calendar_event',
            'readonly'=>true
        ),
        'attendees' => array(
            'title'=>'Attendees',
            'type'=>Field::TYPE_OBJECT_MULTI,
            'subtype'=>'member'
        ),
        'status_id' => array(
            'title'=>'Status',
            'type'=>Field::TYPE_GROUPING,
            'subtype'=>'object_groupings',
            'fkey_table'=>array(
                "key"=>"id",
                "title"=>"name"
            ),
        ),
    ),
    'is_private' => true,
    'default_activity_level' => 1,
);
