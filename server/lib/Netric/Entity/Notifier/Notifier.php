<?php
/**
 * @author Sky Stebnicki <sky.stebnicki@aereus.com>
 * @copyright 2015 Aereus
 */
namespace Netric\Entity\Notifier;

use Netric\Entity\Entity;
use Netric\Entity\ObjType\User;
use Netric\Entity\EntityInterface;
use Netric\EntityQuery;
use Netric\EntityLoader;
use Netric\Entity\ObjType\Activity;
use Netric\EntityQuery\Index\IndexInterface;

/**
 * Manages notifications to followers of an entity
 *
 * Example for comment:
 *
 *  $comment = $entityLoader->create("comment");
 *  $comment->setValue("comment", "[user:1:Test]"); // tag to send notice to user id 1
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
     * Current user
     *
     * @var User
     */
    private $user = null;

    /**
     * Entity loader for getting and saving entities
     *
     * @var EntityLoader
     */
    private $entityLoader = null;

    /**
     * An entity index for querying existing notifications
     *
     * @var IndexInterface
     */
    private $entityIndex = null;

    /**
     * Class constructor and dependency setter
     *
     * @param User $user The current authenticated user
     * @param EntityLoader $entityLoader To create, find, and save entities
     * @param IndexInterface $index An entity index for querying existing notifications
     */
    public function __construct(User $user, EntityLoader $entityLoader, IndexInterface $index)
    {
        $this->user = $user;
        $this->entityLoader = $entityLoader;
        $this->entityIndex = $index;
    }

    /**
     * Send notifications to followers of an entity
     *
     * @param EntityInterface $entity The entity that was just acted on
     * @param string $event The event that is triggering from Activity::VERB_*
     * @return int[] List of notification entities created or updated
     */
    public function send(EntityInterface $entity, $event)
    {
        $objType = $entity->getDefinition()->getObjType();

        // Array of notification entities we either create or update below
        $notificationIds = array();

        // We obviously never want to send notifications about notifications or activities
        if ($objType === 'notification' || $objType === 'activity')
            return $notificationIds;

        /*
         * Get the object reference which is the entity this notice is about.
         * If this is a comment we are adding a notification for, then update
         * the object reference of the notification to point to the entity being
         * commented on rather than the comment itself. That way when the user
         * clicks on the link for the notification, it will take them to the
         * entity being commented on.
         */
        $objReference = ($objType === "comment")
            ? $entity->getValue("obj_reference")
            : Entity::encodeObjRef($objType, $entity->getId());

        // Get a human-readable name to use for this notification
        $name = $this->getNameFromEventVerb($event, $entity->getDefinition()->getTitle());

        // Get followers of the referenced entity
        $followers = $entity->getValue("followers");

        // If no values, then return empty array
        if (!is_array($followers))
            return $notificationIds;

        foreach ($followers as $userId)
        {
            /*
             * Create a new notification if it is not the current user - we don't want
             * to notify a user if they are the one performing the action.
             */
            if ($userId != $this->user->getId())
            {
                // Create new notification, or update an existing unseen one
                $notification = $this->getNotification($objReference, $userId);
                $notification->setValue("name", $name);
                $notification->setValue("description", $entity->getDescription());
                $notification->setValue("f_email", true);
                $notification->setValue("f_popup", false);
                $notification->setValue("f_sms", false);
                $notification->setValue("f_seen", false);
                $notificationIds[] = $this->entityLoader->save($notification);
            }
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
     * @param User $user Optional user to set seen for, otherwise use current logged in user
     */
    public function markNotificationsSeen(EntityInterface $entity, User $user = null)
    {
        // If we did not manually pass a user, then use the current user
        if (!$user)
            $user = $this->user;

        // Get the object type
        $objReference = Entity::encodeObjRef(
            $entity->getDefinition()->getObjType(),
            $entity->getId()
        );

        $query = new EntityQuery("notification");
        $query->where("owner_id")->equals($user->getId());
        $query->andWhere("obj_reference")->equals($objReference);
        $query->andWhere("f_seen")->equals(false);
        $result = $this->entityIndex->executeQuery($query);
        $num = $result->getNum();
        for ($i = 0; $i < $num; $i++)
        {
            $notification = $result->getEntity($i);
            $notification->setValue("f_seen", true);
            $this->entityLoader->save($notification);
        }
    }

    /**
     * Either get an existing notification if unseen, or create a new one for $objReference
     *
     * @param string $objReference
     * @param int $userId
     * @return EntityInterface
     */
    private function getNotification($objReference, $userId)
    {
        // Initialize the notification variable to return
        $notification = null;

        /*
         * Query past notification entities to see if an entity is outstanding
         * and not yet seen for this entity/object reference.
         */
        $query = new EntityQuery("notification");
        $query->where("owner_id")->equals($userId);
        $query->andWhere("obj_reference")->equals($objReference);
        $query->andWhere("creator_id")->equals($this->user->getId());
        $query->andWhere("f_seen")->equals(false);

        // Make sure we get the latest notification if there are multiple
        $query->orderBy("ts_updated", EntityQuery\OrderBy::DESCENDING);

        // Get the results
        $result = $this->entityIndex->executeQuery($query);
        if ($result->getNum())
        {
            $notification = $result->getEntity(0);
        }
        else
        {
            // There are no outstanding/unseen notifications, create a new one
            $notification = $this->entityLoader->create("notification");
            $notification->setValue("obj_reference", $objReference);
            $notification->setValue("owner_id", $userId);
            $notification->setValue("creator_id", $this->user->getId(), $this->user->getName());
        }

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
        if (Activity::VERB_CREATED == $event)
        {
            return "Added " . $objTypeTitle;
        }
        else
        {
            return ucfirst($event) . " " . $objTypeTitle;
        }
    }
}
