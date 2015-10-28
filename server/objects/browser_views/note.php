<?php
/**
 * Return browser views for entity of object type 'note'
 */
namespace objects\browser_views;

use Netric\EntityQuery\Where;

return array(
    'default'=> array(
        'obj_type' => 'note',
        'conditions' => array(
            'user' => array(
                'blogic' => Where::COMBINED_BY_AND,
                'field_name' => 'user_id',
                'operator' => Where::OPERATOR_EQUAL_TO,
                'value' => -3
            ),
        ),
    ),
);
