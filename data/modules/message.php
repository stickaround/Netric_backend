<?php

/**
 * Return navigation for entity of messages
 */

namespace modules\navigation;

use Netric\Account\Module\LeftNavItemTypes;

return [
    "title" => "Messages",
    "icon" => "ChatBubbleIcon",
    "default_route" => "direct",
    "name" => "message",
    "short_title" => 'Chat',
    "scope" => 'system',
    "sort_order" => 2,
    "f_system" => true,
    "navigation" => [
        [
            "type" => LeftNavItemTypes::ENTITY_MODAL,
            "title" => "New Message",
            "route" => "newdirect",
            "icon" => "ChatIcon",
            "objType" => "chat_room",
            "data" => [
                "scope" => "direct"
            ]
        ],
        [
            "type" => LeftNavItemTypes::ENTITY_MODAL,
            "title" => "New Room",
            "route" => "newroom",
            "icon" => "ForumIcon",
            "objType" => "chat_room",
            "data" => [
                "scope" => "channel"
            ]
        ],
        [
            "type" => LeftNavItemTypes::HEADER,
            "title" => "Rooms",
            "route" => "rooms",
        ],
        [
            "type" => LeftNavItemTypes::ENTITY_BROWSE_LEFTNAV,
            "route" => "rooms",
            "browser_view" => "my_rooms",
            "objType" => "chat_room"
        ],
        [
            "title" => "Browse All Rooms",
            "type" => LeftNavItemTypes::ENTITY_BROWSE,
            "route" => "rooms",
            "objType" => "chat_room",
            "browser_view" => "my_rooms",
            "icon" => "SearchIcon",
        ],
        [
            "type" => LeftNavItemTypes::HEADER,
            "title" => "Direct",
            "route" => "direct",
        ],
        [
            "type" => LeftNavItemTypes::ENTITY_BROWSE_LEFTNAV,
            "route" => "direct",
            "browser_view" => "my_direct_messages",
            "objType" => "chat_room"
        ],
        [
            "title" => "All Direct Messages",
            "type" => LeftNavItemTypes::ENTITY_BROWSE,
            "route" => "direct",
            "objType" => "chat_room",
            "icon" => "SearchIcon",
            "browser_view" => "my_direct_messages",
        ],
    ],
];
