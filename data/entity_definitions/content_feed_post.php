<?php
namespace data\entity_definitions;

return array(
    'parent_field' => 'parent_id',
    'uname_settings' => 'feed_id:title',
    'fields' => array(
        'title' => array(
            'title'=>'Title',
            'type'=>'text',
            'subtype'=>'256',
            'readonly'=>false,
            'required'=>true,
        ),

        'author' => array(
            'title'=>'Author',
            'type'=>'text',
            'subtype'=>'256',
            'readonly'=>false,
        ),

        'data' => array(
            'title'=>'Body', 'type'=>'text', 'subtype'=>'', 'readonly'=>false
        ),

        'image' => array(
            'title'=>'Image',
            'type'=>'object',
            'subtype'=>'file',
            'readonly'=>false
        ),

        'f_publish' => array(
            'title'=>'Published',
            'type'=>'bool',
            'subtype'=>'',
            'readonly'=>false,
            'default'=>array("value"=>"f", "on"=>"null"),
        ),

        "time_publish" => array(
            'title'=>'Publish After', 'type'=>'timestamp', 'subtype'=>'', 'readonly'=>false
        ),

        "time_expires" => array(
            'title'=>'Expires', 'type'=>'timestamp', 'subtype'=>'', 'readonly'=>false
        ),

        "time_entered" => array(
            'title'=>'Post Date',
            'type'=>'timestamp',
            'subtype'=>'',
            'readonly'=>false,
            "default"=>array("value"=>"now", "on"=>"create"),
        ),

        "ts_updated" => array(
            'title'=>'Updated',
            'type'=>'timestamp',
            'subtype'=>'',
            'readonly'=>true,
            "default"=>array("value"=>"now", "on"=>"update"),
        ),

        "user_id" => array(
            'title'=>'User',
            'type'=>'object',
            'subtype'=>'user',
            'readonly'=>false,
            "default"=>array("value"=>"-3", "on"=>"null"),
        ),

        "feed_id" => array(
            'title'=>'Feed',
            'type'=>'object',
            'subtype'=>'content_feed',
            'readonly'=>false,
            'required'=>true,
        ),

        // Type: Article, Page, Widget
        /*
        'type_id' => array(
            'title'=>'Type',
            'type'=>'fkey',
            'subtype'=>'object_groupings',
            'fkey_table'=>array(
                "key"=>"id",
                "title"=>"name",
                "parent"=>"parent_id",
                "ref_table"=>array(
                    "table"=>"object_grouping_mem",
                    "this"=>"object_id",
                    "ref"=>"grouping_id",
                ),
            ),
        ),
         */

        'status_id' => array(
            'title'=>'Status',
            'type'=>'fkey',
            'subtype'=>'object_groupings',
            'required'=> true,
            'fkey_table'=>array(
                "key"=>"id",
                "title"=>"name",
                "ref_table"=>array(
                    "table"=>"object_grouping_mem",
                    "this"=>"object_id",
                    "ref"=>"grouping_id"
                )
            )
        ),

        // Type : Post, Page, Widget
        'type' => array(
            'title'=>'Type',
            'type'=>'text',
            'subtype'=>'32',
            'optional_values'=>array("post"=>"Post", "page"=>"Page", "widget"=>"Widget")
        ),

        // Posts can be linked to sites
        "site_id" => array(
            'title'=>'Site',
            'type'=>'object',
            'subtype'=>'cms_site',
            'readonly'=>false,
        ),

        "categories" => array(
            'title'=>'Categories',
            'type'=>'fkey_multi',
            'subtype'=>'object_groupings',
            'fkey_table'=>array(
                "key"=>"id",
                "title"=>"name",
                "parent"=>"parent_id",
                "filter"=>array("feed_id"=>"feed_id"),
                "ref_table"=>array(
                    "table"=>"object_grouping_mem",
                    "this"=>"post_id",
                    "ref"=>"category_id"
                ),
            ),
        ),

        // The parent post
        "parent_id" => array(
            'title'=>'Parent',
            'type'=>'object',
            'subtype'=>'content_feed_post',
            'readonly'=>false,
        ),
    ),
);
