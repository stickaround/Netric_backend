<?php

/**
 * Return browser views for entity of object type 'wall_post'
 */

namespace data\browser_views;

use Netric\Entity\ObjType\UserEntity;
use Netric\EntityQuery\Where;

return array(
    'news_feed' => array(
        'obj_type' => 'wall_post',
        'name' => 'News Feed',
        'description' => '',
        'default' => true,
        'order_by' => array(
            'sort_order' => array(
                'field_name' => 'ts_entered',
                'direction' => 'desc',
            ),
        ),
        'table_columns' => array('owner_id', 'ts_entered', 'obj_reference', 'comment', 'notified', 'sent_by')
    ),

    'my_wall_posts' => array(
        'obj_type' => 'wall_post',
        'name' => 'My Status Updates',
        'description' => '',
        'default' => false,
        'conditions' => array(
            'owner' => array(
                'blogic' => Where::COMBINED_BY_AND,
                'field_name' => 'owner_id',
                'operator' => Where::OPERATOR_EQUAL_TO,
                'value' => UserEntity::USER_CURRENT,
            ),
        ),
        'order_by' => array(
            'sort_order' => array(
                'field_name' => 'ts_entered',
                'direction' => 'desc',
            ),
        ),
        'table_columns' => array('owner_id', 'ts_entered', 'obj_reference', 'comment', 'notified', 'sent_by')
    ),
);