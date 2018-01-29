<?php
namespace data\entity_definitions;
use Netric\EntityDefinition\Field;

return array(
    'fields' => array(
        'name' => array(
            'title'=>'Name',
            'type'=>Field::TYPE_TEXT,
            'subtype'=>'256',
            'readonly'=>false
        ),
        'notes' => array(
            'title'=>'Notes',
            'type'=>Field::TYPE_TEXT,
            'subtype'=>'',
            'readonly'=>false
        ),
        'rating' => array(
            'title'=>'Rating',
            'type'=>Field::TYPE_NUMBER,
            'subtype'=>'',
            'readonly'=>false
        ),
        'product' => array(
            'title'=>'Product',
            'type'=>Field::TYPE_OBJECT,
            'subtype'=>'product',
            'readonly'=>false
        ),
        'creator_id' => array(
            'title'=>'Reviewer',
            'type'=>Field::TYPE_OBJECT,
            'subtype'=>'user',
            'readonly'=>true,
            'default'=>array("value"=>"-3", "on"=>"null")
        ),
    ),
    'aggregates' => array(
        'avg_rating' => array(
            'type' => 'avg',
            'calc_field' => 'rating',
            'ref_obj_update' => 'product',
            'obj_field_to_update' => 'rating',
        ),
    ),
);
