<?php
/**
 * Provides extensions for the Task object
 *
 * @author Marl Tumulak <marl.tumulak@aereus.com>
 * @copyright 2016 Aereus
 */
namespace Netric\Entity\ObjType;

use Netric\ServiceManager\AccountServiceManagerInterface;
use Netric\Entity\Entity;
use Netric\Entity\EntityInterface;

/**
 * Task represents a single task entity
 */
class TaskEntity extends Entity implements EntityInterface
{
    /**
     * Constant statuses
     */
    const STATUS_TODO = 'ToDo';
    const STATUS_IN_PROGRESS = 'In-Progress';
    const STATUS_COMPLETED = 'Completed';
    const STATUS_IN_TEST = "In-Test";
    const STATUS_IN_REVIEW = "In-Review";

    /**
     * Constant Priorities
     */
    const PRIORITY_HIGH = 'High';
    const PRIORITY_MEDIUM = 'Medium';
    const PRIORITY_LOW = 'Low';

    /**
     * Constant Types
     */
    const TYPE_ENHANCEMENT = 'Enhancement';
    const TYPE_DEFECT = 'Defect';

    /**
     * Callback function used for derrived subclasses
     *
     * @param \Netric\ServiceManager\AccountServiceManagerInterface $sm Service manager used to load supporting services
     */
    public function onBeforeSave(AccountServiceManagerInterface $sm)
    {
        if ($this->getValue('status_id')) {
            $this->setValue(
                'done',
                ($this->getValueName('status_id') === self::STATUS_COMPLETED)
            );
        }
    }

    /**
     * Callback function used for derrived subclasses
     *
     * @param AccountServiceManagerInterface $sm Service manager used to load supporting services
     */
    public function onAfterSave(AccountServiceManagerInterface $sm)
    {
    }

    /**
     * Called right before the entity is purged (hard delete)
     *
     * @param AccountServiceManagerInterface $sm Service manager used to load supporting services
     */
    public function onBeforeDeleteHard(AccountServiceManagerInterface $sm)
    {
    }

    /**
     * Override the default because files can have different icons depending on whether or not this is completed
     *
     * @return string The base name of the icon for this object if it exists
     */
    public function getIconName()
    {
        $done = $this->getValue("done");

        if ($done === 't' || $done === true) {
            return "task_on";
        } else {
            return "task";
        }
    }
}
