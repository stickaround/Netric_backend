<?php
namespace data\entity_definitions;

use Netric\Entity\ObjType\UserEntity;
use Netric\EntityDefinition\Field;

return array(
    'fields' => array(
        'name' => array(
            'title' => 'Name',
            'type' => Field::TYPE_TEXT,
            'subtype' => '512',
            'readonly' => false,
        ),
        'f_public' => array(
            'title' => 'Public',
            'type' => Field::TYPE_BOOL,
            'subtype' => '',
            'readonly' => false,
            'default' => array(
                "on" => "null",
                "value" => "f",
            ),
        ),
        'f_view' => array(
            'title' => 'Show Events',
            'type' => Field::TYPE_BOOL,
            'subtype' => '',
            'readonly' => false
        ),
        'def_cal' => array(
            'title' => 'Default',
            'type' => Field::TYPE_BOOL,
            'subtype' => '',
            'readonly' => false
        ),
    ),
);
