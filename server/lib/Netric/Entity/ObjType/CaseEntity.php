<?php
/**
 * Provides extensions for the Case object
 *
 * @author Marl Tumulak <marl.tumulak@aereus.com>
 * @copyright 2016 Aereus
 */
namespace Netric\Entity\ObjType;

use Netric\ServiceManager\ServiceLocatorInterface;
use Netric\Entity\Entity;
use Netric\Entity\EntityInterface;

/**
 * Case represents a single case on any entity
 */
class CaseEntity extends Entity implements EntityInterface
{
    /**
     * Callback function used for derrived subclasses
     *
     * @param \Netric\ServiceManager\ServiceLocatorInterface $sm Service manager used to load supporting services
     */
    public function onBeforeSave(ServiceLocatorInterface $sm)
    {
    }

    /**
     * Callback function used for derrived subclasses
     *
     * @param ServiceLocatorInterface $sm Service manager used to load supporting services
     */
    public function onAfterSave(ServiceLocatorInterface $sm)
    {
    }

    /**
     * Called right before the entity is purged (hard delete)
     *
     * @param ServiceLocatorInterface $sm Service manager used to load supporting services
     */
    public function onBeforeDeleteHard(ServiceLocatorInterface $sm)
    {

    }
}

