<?php

declare(strict_types=1);

namespace Netric\Entity\Notifier\Sender;

use Netric\Entity\EntityInterface;
use Netric\Entity\ObjType\NotificationEntity;
use Netric\Entity\ObjType\UserEntity;
use Netric\Entity\EntityLoader;
use Netric\EntityDefinition\ObjectTypes;
use Netric\Log\LogInterface;
use Netric\Mail\MailSystemInterface;
use Netric\Mail\SenderService;

class PublicUserEmailSender implements NotificationSenderInterface
{
    /**
     * Entity loader to get referenced entities
     *
     * @var EntityLoader
     */
    private EntityLoader $entityLoader;

    /**
     * Used to send emails
     *
     * @var SenderService
     */
    private SenderService $mailSender;

    /**
     * Mailsystem to get addresses and domains mostly
     *
     * @var MailSystemInterface
     */
    private MailSystemInterface $mailSystem;

    /**
     * Logger
     *
     * @var LogInterface
     */
    private LogInterface $log;

    /**
     * Constructor
     *
     * @param EntityLoader $entityLoader
     * @param SenderService $mailSender
     * @param MailSystemInterface $mailSystem
     */
    public function __construct(
        EntityLoader $entityLoader,
        SenderService $mailSender,
        MailSystemInterface $mailSystem,
        LogInterface $log
    ) {
        $this->entityLoader = $entityLoader;
        $this->mailSender = $mailSender;
        $this->mailSystem = $mailSystem;
        $this->log = $log;
    }

    /**
     * Send an email notification to a public user
     *
     * @param NotificationEntity $notification
     * @param UserEntity $user The user who performed the action resulting in a notification
     * @return bool True if a notice was sent, otherwise false (not necessarily an error)
     */
    public function sendNotification(NotificationEntity $notification, UserEntity $user): bool
    {
        // Make sure the notification has an owner
        if (empty($notification->getValue("owner_id"))) {
            return false;
        }

        // Get the user that this notice belongs to
        $targetUser = $this->entityLoader->getEntityById(
            $notification->getValue("owner_id"),
            $user->getAccountId()
        );

        // Make sure the user has an email
        if (!$targetUser || !$targetUser->getValue("email")) {
            return false;
        }

        // Get the referenced entity
        $objReference = $notification->getValue("obj_reference");
        $referencedEntity = $this->entityLoader->getEntityById(
            $objReference,
            $user->getAccountId()
        );

        // Set the body
        $body = $notification->getValue("description");

        // Set from email to be a dynamic comment dropbox so they can reply
        // and a new comment will be created.
        $fromEmail = 'comment.' .
            $referencedEntity->getEntityId() .
            '@' .
            $this->mailSystem->getDefaultDomain($user->getAccountId());
        $fromName = "Support";

        // Handle a ticket comment associated with a support channel
        if ($referencedEntity->getObjType() === ObjectTypes::TICKET) {
            if ($referencedEntity->getValue('channel_id')) {
                $channel = $this->entityLoader->getEntityById(
                    $referencedEntity->getValue('channel_id'),
                    $user->getAccountId()
                );

                if ($channel && $channel->getValue('email_account_id')) {
                    $emailAccount = $this->entityLoader->getEntityById(
                        $channel->getValue('email_account_id'),
                        $user->getAccountId()
                    );

                    if ($emailAccount) {
                        $fromEmail = $emailAccount->getValue('address');
                        $fromName = $emailAccount->getValue('name');
                    }
                }
            }
        }

        // Set the message ID to include the refreenced entity and notification
        $headers = [
            'message-id' => $this->generateMessageId($referencedEntity, $notification)
        ];

        $ret = $this->mailSender->send(
            $targetUser->getValue("email"),
            $targetUser->getValue("full_name"),
            $fromEmail,
            $fromName,
            $notification->getValue('name'),
            $body,
            $headers
        );

        return $ret;
    }

    /**
     * Generate a unique message ID
     *
     * @param EntityInterface $refrencedEntity
     * @param EntityInterface $notification
     * @return string
     */
    private function generateMessageId(EntityInterface $refrencedEntity, EntityInterface $notification): string
    {
        $messageId = $refrencedEntity->getEntityId() . '.' . $notification->getEntityId();
        $messageId .= "@" . $this->mailSystem->getDefaultDomain($refrencedEntity->getAccountId());
        return $messageId;
    }
}
