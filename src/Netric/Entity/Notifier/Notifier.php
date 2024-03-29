<?php

namespace Netric\Entity\Notifier;

use Netric\Entity\EntityEvents;
use Netric\Entity\ObjType\UserEntity;
use Netric\Entity\EntityInterface;
use Netric\EntityQuery\EntityQuery;
use Netric\EntityQuery\OrderBy;
use Netric\Entity\EntityLoader;
use Netric\Entity\Notifier\Sender\PublicUserEmailSender;
use Netric\Entity\ObjType\ActivityEntity;
use Netric\Entity\ObjType\NotificationEntity;
use Netric\EntityQuery\Index\IndexInterface;
use Netric\EntityDefinition\ObjectTypes;
use Netric\Log\LogInterface;
use Netric\Mail\SenderService;
use NotificationPusherSdk\NotificationPusherClientInterface;
use Ramsey\Uuid\Uuid;

/**
 * Manages notifications to followers of an entity
 *
 * Example for comment:
 *
 *  $comment = $entityLoader->create("comment", $currentUser->getAccountId());
 *  $comment->setValue("comment", "@sky"); // tag to send notice to user sky
 *  $entityLoader->save($comment);
 *  $notifier = $sl->get("Netric/Entity/Notifier/Notifier");
 *  $notifier->send($comment, "create");
 *
 * This will create a new unread notification for user id 1 if they are not the
 * ones creating the comment. Users do not need to be notified of comments they add
 * or updates they performed on entities.
 */
class Notifier
{
    /**
     * Entity loader for getting and saving entities
     *
     * @var EntityLoader
     */
    private EntityLoader $entityLoader;

    /**
     * An entity index for querying existing notifications
     *
     * @var IndexInterface
     */
    private IndexInterface $entityIndex;

    /**
     * Client used to connect with the notification pusher server
     */
    private NotificationPusherClientInterface $notificationPusher;

    /**
     * Service used to send smtp email
     *
     * @var SenderService
     */
    private PublicUserEmailSender $publicEmailSender;

    /**
     * Optional initialized log
     *
     * @var LogInterface
     */
    private LogInterface $log;

    /**
     * Class constructor and dependency setter
     *
     * @param EntityLoader $entityLoader To create, find, and save entities
     * @param IndexInterface $index An entity index for querying existing notifications
     * @param NotificationPusherClientInterface $notificationPusher Used for push notifications
     */
    public function __construct(
        EntityLoader $entityLoader,
        IndexInterface $index,
        NotificationPusherClientInterface $notificationPusher,
        PublicUserEmailSender $publicEmailSender
    ) {
        $this->entityLoader = $entityLoader;
        $this->entityIndex = $index;
        $this->notificationPusher = $notificationPusher;
        $this->publicEmailSender = $publicEmailSender;
    }

    /**
     * Used for debugging
     *
     * @param LogInterface $log
     * @return void
     */
    public function setLog(LogInterface $log)
    {
        $this->log = $log;
    }

    /**
     * Send notifications to followers of an entity
     *
     * @param EntityInterface $entity The entity that was just acted on
     * @param string $event The event that is triggering from EntityEvents::EVENT_*
     * @param UserEntity $user The user performing the event
     * @param string $changedDescription The description of the change that took place
     * @return int[] List of notification entities created or updated
     */
    public function send(EntityInterface $entity, string $event, UserEntity $user, string $changedDescription = '')
    {
        $objType = $entity->getDefinition()->getObjType();

        // Array of notification entities we either create or update below
        $notificationIds = [];

        // We never want to send notifications about notifications or activities
        // Or changes to users (since they change themselves all the time)
        // Or notifications from anonmymous or system users
        if (
            $objType == ObjectTypes::NOTIFICATION ||
            $objType == ObjectTypes::ACTIVITY ||
            $objType == ObjectTypes::USER ||
            $user->isSystem() ||
            $user->isAnonymous()
        ) {
            return $notificationIds;
        }
        $objReference = $entity->getEntityId();

        // Get a human-readable name to use for this notification
        $name = $this->getNameFromEventVerb($event, $entity->getDefinition()->getTitle());

        $description = ($changedDescription) ? $changedDescription : $entity->getDescription();

        // Get followers of the referenced entity
        $followers = $this->getInterestedUsers($entity, $user);

        foreach ($followers as $followerId) {
            // If the follower id is not a valid user id then just skip
            if (!Uuid::isValid($followerId)) {
                continue;
            }

            $follower = $this->entityLoader->getEntityById($followerId, $user->getAccountId());

            /**
             * Make sure the follower is valid:
             *
             * 1. Was not deleted
             * 2. Not the same as the user performing the action - no need to notify them they did something
             * 3. Not a meta user (user poiinter like creator/owner)
             * 4. Not a public user IF the entity is not public
             */
            if (
                !$follower ||
                $follower->getEntityId() == $user->getEntityId() ||
                $follower->getValue('type') == UserEntity::TYPE_META ||
                $follower->getValue('type') == UserEntity::TYPE_SYSTEM ||
                ($follower->getValue('type') == UserEntity::TYPE_PUBLIC &&
                    $entity->getValue('is_public') !== true)
            ) {
                // Skip
                continue;
            }

            // If the verb is create or sent, then check to see if the entity
            // has already been seen by the user we are about to send the notification to
            if ($event === EntityEvents::EVENT_CREATE) {
                if (in_array($followerId, $entity->getValue('seen_by'))) {
                    // Skip because the user has already seen the entity
                    continue;
                }
            }

            /*
             * Get the object reference which is the entity this notice is about.
             * If this is a comment we are adding a notification for, then update
             * the object reference of the notification to point to the entity being
             * commented on rather than the comment itself. That way when the user
             * clicks on the link for the notification, it will take them to the
             * entity being commented on.
             */
            if ($objType == ObjectTypes::COMMENT) {
                $objReference = $entity->getValue("obj_reference");
                $ownerName = $entity->getValueName("owner_id");
                $followerName = $entity->getValueName('followers', $followerId);
                $description = $entity->getValue("comment");
                $name = "$ownerName added comment";

                // Check if the user is being called out in the comment, if so, then let's change the name.
                if (preg_match('/(@' . $followerName . ')/', $description)) {
                    $name = "$ownerName directed a comment at you";
                }
            }

            /*
             * If this is a chat message, point the object reference
             * to the room rather than the message. That way when the user
             * clicks on the link for the notification, it will take them to the
             * chat room.
             */
            if ($objType == ObjectTypes::CHAT_MESSAGE) {
                $objReference = $entity->getValue("chat_room");
                $ownerName = $entity->getValueName("owner_id");
                $followerName = $entity->getValueName('followers', $followerId);
                $description = $entity->getValue("body");
                $name = "$ownerName sent a message";

                // Check if the user is being called out in the message, if so, then let's change the name.
                if (preg_match('/(@' . $followerName . ')/', $description)) {
                    $name = "$ownerName directed a message at you";
                }
            }

            /*
             * If this is a reaction, point the object reference
             * to the entity reacted to.
             */
            if ($objType == ObjectTypes::USER_REACTION) {
                $objReference = $entity->getValue("obj_reference");
                $ownerName = $entity->getValueName("owner_id");
                $description = $entity->getValueName("obj_reference", $objReference);
                $name = "$ownerName reacted with " . $entity->getValue("reaction");
                // Replace icon names with unicode characters for notifications
                $name = str_replace("ThumbUpIcon", "\u{1F44D}", $name);
            }

            // Create new notification, or update an existing unseen one
            $notification = $this->getNotification($objReference, $followerId, $user->getAccountId());
            $notification->setValue("name", $name);
            $notification->setValue("description", $description);
            $notification->setValue("is_seen", false);
            $notificationIds[] = $this->entityLoader->save($notification, $user);

            $this->sendNotification($notification, $user, $follower);
        }

        return $notificationIds;
    }

    /**
     * If a user views an entity, we should mark any unread notifications as read
     *
     * An example of this might be that we send a notification to a user that
     * a new task was created for them, then they go view the task by clicking
     * on the link in the email. We would expect this function to mark the notification
     * we sent them as read when they view the task.
     *
     * @param EntityInterface $entity The entity that was seen by a user
     * @param UserEntity $user Optional user to set seen for, otherwise use current logged in user
     */
    public function markNotificationsSeen(EntityInterface $entity, UserEntity $user)
    {
        $query = new EntityQuery(ObjectTypes::NOTIFICATION, $user->getAccountId());
        $query->where("owner_id")->equals($user->getEntityId());
        $query->andWhere("obj_reference")->equals($entity->getEntityId());
        $query->andWhere("is_seen")->equals(false);
        $result = $this->entityIndex->executeQuery($query);
        $num = $result->getNum();
        for ($i = 0; $i < $num; $i++) {
            $notification = $result->getEntity($i);
            $notification->setValue("is_seen", true);
            $this->entityLoader->save($notification, $user);
        }
    }

    /**
     * Either get an existing notification if unseen, or create a new one for $objReference
     *
     * @param string $objReference The id of the entity reference
     * @param string $userGuid The id of the user
     * @param string $accountId
     * @return EntityInterface
     */
    private function getNotification(string $objReference, string $userGuid, string $accountId)
    {
        // Initialize the notification variable to return
        $notification = null;

        /*
         * Query past notification entities to see if an entity is outstanding
         * and not yet seen for this entity/object reference.
         */
        $query = new EntityQuery(ObjectTypes::NOTIFICATION, $accountId);
        $query->where("owner_id")->equals($userGuid);
        $query->andWhere("obj_reference")->equals($objReference);
        $query->andWhere("is_seen")->equals(false);

        // Make sure we get the latest notification if there are multiple
        $query->orderBy("ts_updated", OrderBy::DESCENDING);

        // Get the results
        $result = $this->entityIndex->executeQuery($query);
        if ($result->getNum()) {
            return $result->getEntity(0);
        }

        // There are no outstanding/unseen notifications, create a new one
        $notification = $this->entityLoader->create(ObjectTypes::NOTIFICATION, $accountId);
        $notification->setValue("obj_reference", $objReference);
        $notification->setValue("owner_id", $userGuid);

        return $notification;
    }

    /**
     * Construct a human-readable name from the event verb
     *
     * @param string $event The action taken on the entity
     * @param string $objTypeTitle The title of the object type we are acting on
     * @return string The title for the notification
     */
    private function getNameFromEventVerb($event, $objTypeTitle)
    {
        switch ($event) {
            case EntityEvents::EVENT_CREATE:
                return "Added " . $objTypeTitle;
            default:
                return ucfirst($event) . "d " . $objTypeTitle;
        }
    }

    /**
     * Return list of users that should be notified of an event
     *
     * @param EntityInterface $entity
     * @param UserEntity $user The user performing the action
     * @return array
     */
    private function getInterestedUsers(EntityInterface $entity, UserEntity $user): array
    {
        $objType = $entity->getDefinition()->getObjType();
        $followers = [];

        // Get followers of the referenced entity
        if (is_array($entity->getValue("followers"))) {
            $followers = $entity->getValue("followers");
        }

        /*
         * If the entity being created is a comment, then we want to
         * check the followers of the entity being commented on.
         */
        $objReference = $entity->getValue("obj_reference");
        if ($objType == ObjectTypes::COMMENT && Uuid::isValid($objReference)) {
            $refEntity = $this->entityLoader->getEntityById($objReference, $user->getAccountId());
            if ($refEntity && is_array($refEntity->getValue('followers'))) {
                $followers = array_unique(array_merge($followers, $refEntity->getValue('followers')));
            }
        }

        return $followers;
    }

    /**
     * Subscribe to a push channel
     *
     * @param string $userId The ID of the netric user
     * @param string $channel One of NotificationPusherClientInterface::CHANNEL_*
     * @param array $data Data from the client to save in the subscription
     * @return bool true on success, false on failure
     */
    public function subscribeToPush(string $userId, string $channel, array $data): bool
    {
        return $this->notificationPusher->subscribe(
            'netric',
            $userId,
            $channel,
            $data
        );
    }

    /**
     * Send notification to various channels
     *
     * If the user is a public user, we send an email, otherwise we use push notifications
     *
     * @param NotificationEntity $notification Notification to send
     * @param UserEntity $user The user who performed the action causing the notification
     * @param EntityInterface $followerEntity The follower we are sending the notification to
     * @return void
     */
    private function sendNotification(NotificationEntity $notification, UserEntity $user, EntityInterface $followerEntity): void
    {
        // Public users get email notifications, internal users get push (below)
        if ($followerEntity->getValue('type') === UserEntity::TYPE_PUBLIC) {
            $this->publicEmailSender->sendNotification($notification, $user);
            return;
        }

        $this->sendNotificationPush($notification, $user);
    }

    /**
     * Push a notification to a push channel like chrome html push or apple push notificaiton service
     *
     * @param NotificationEntity $notification Notification to send
     * @param UserEntity $user The user who performed the action causing the notification
     * @return bool true on success
     */
    public function sendNotificationPush(NotificationEntity $notification, UserEntity $user): bool
    {
        // Make sure the notification has an owner or a creator
        if (
            empty($notification->getValue("owner_id")) ||
            empty($notification->getValue("name")) ||
            empty($notification->getValue("description"))
        ) {
            return false;
        }

        return $this->notificationPusher->send(
            'netric',
            $notification->getValue("owner_id"),
            $notification->getValue("name"),
            $notification->getValue("description"),
            ['entityId' => $notification->getValue("obj_reference")]
        );
    }
}
