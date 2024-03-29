<?php

namespace Netric\Entity\ObjType;

use Netric\ServiceManager\ServiceLocatorInterface;
use Netric\Entity\Entity;
use Netric\Entity\EntityInterface;
use Netric\Entity\ObjType\UserEntity;
use Netric\EntityDefinition\Field;
use Netric\EntityDefinition\ObjectTypes;
use Ramsey\Uuid\Uuid;

/**
 * Comment represents a single comment on any entity
 */
class CommentEntity extends Entity implements EntityInterface
{
    /**
     * Callback function used for derrived subclasses
     *
     * @param ServiceLocatorInterface $serviceLocator ServiceLocator for injecting dependencies
     * @param UserEntity $user The user that is acting on this entity
     */
    public function onBeforeSave(ServiceLocatorInterface $serviceLocator, UserEntity $user)
    {
        $objReference = $this->getValue('obj_reference');
        $entityCommentedOn = $this->getEntityLoader()->getEntityById(
            $objReference,
            $user->getAccountId()
        );

        // Set comments associations to all directly associated objects if new
        if ($entityCommentedOn) {
            // Update the num_comments field of the entity we are commenting on
            // Only if the comment is new and/or just deleted
            if ($this->getValue('revision') <= 1 || ($this->isArchived() && $this->fieldValueChanged('f_deleted'))) {
                // Determine if we should increment or decrement
                $added = ($this->isArchived()) ? false : true;
                $entityCommentedOn->setHasComments($added);
            }

            // Add object references to the list of associations
            $this->addMultiValue("associations", $entityCommentedOn->getEntityId(), $entityCommentedOn->getName());

            /*
             * Copy associations for everything but status updates
             * since status updates are really just like comments themselves.
             * Only do this if it's a new comment - only needed once
             */
            if ($entityCommentedOn->getObjType() != ObjectTypes::WALL_POST && !$this->getEntityId()) {
                $fields = $entityCommentedOn->getDefinition()->getFields();
                foreach ($fields as $field) {
                    if ($field->type == Field::TYPE_OBJECT && ($field->subtype || $field->name === "obj_reference")) {
                        $val = $entityCommentedOn->getValue($field->name);
                        if (Uuid::isValid($val)) {
                            $this->addMultiValue("associations", $val);
                        }
                    }
                }
            }

            // Make sure followers of this comment are synchronized with the entity
            $this->syncFollowers($entityCommentedOn);

            // Save the entity we are commenting on if there were changes
            if ($entityCommentedOn->isDirty()) {
                $this->getEntityLoader()->save($entityCommentedOn, $user);
            }

            // Set who this was sent by if not already set
            if (!$this->getValue('sent_by') && $user->getEntityId()) {
                $this->setValue("sent_by", $user->getEntityId());
            }
        }
    }
}
