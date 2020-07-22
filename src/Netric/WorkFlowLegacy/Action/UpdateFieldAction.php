<?php

/**
 * @author Sky Stebnicki, sky.stebnicki@aereus.com
 * @copyright Copyright (c) 2015 Aereus Corporation (http://www.aereus.com)
 */

namespace Netric\WorkFlowLegacy\Action;

use Netric\EntityDefinition\Field;
use Netric\Entity\EntityInterface;
use Netric\Entity\EntityLoader;
use Netric\WorkFlowLegacy\WorkFlowLegacyInstance;
use Netric\Error\Error;

/**
 * Action to update the field of an entity
 */
class UpdateFieldAction extends AbstractAction implements ActionInterface
{
    /**
     * Execute this action
     *
     * @param WorkFlowLegacyInstance $workflowInstance The workflow instance we are executing in
     * @return bool true on success, false on failure
     */
    public function execute(WorkFlowLegacyInstance $workflowInstance)
    {
        // Get the entity we are acting on
        $entity = $workflowInstance->getEntity();

        // Get merged params
        $params = $this->getParams($entity);

        if (!isset($params['update_field']) || empty($params['update_field'])) {
            $this->errors[] = new Error("Could not update field because update_field param was not set");
            return false;
        }

        // Get the field we are updating
        $field = $entity->getDefinition()->getField($params['update_field']);

        if (!$field) {
            $this->errors[] = new Error("Tried to update a field that does not exist: " . $params['update_field']);
            return false;
        }

        // Update the entity field and save
        if ($field->type == FIELD::TYPE_GROUPING_MULTI || $field->type == FIELD::TYPE_OBJECT_MULTI) {
            $entity->addMultiValue($field->name, $params['update_value']);
        } else {
            $entity->setValue($field->name, $params['update_value']);
        }

        // Save changes
        $this->entityLoader->save($entity);

        return true;
    }
}