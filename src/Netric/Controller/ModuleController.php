<?php

namespace Netric\Controller;

use Netric\Mvc;
use Netric\Account\Module\ModuleServiceFactory;

/**
 * Controller for interacting with entities
 */
class ModuleController extends Mvc\AbstractAccountController
{
    /**
     * Get the definition of an account
     */
    public function getGetAction()
    {
        $params = $this->getRequest()->getParams();

        if (!isset($params['moduleName'])) {
            return $this->sendOutput(['error' => "moduleName is a required query param"]);
        }

        // Get the service manager of the current user
        $serviceManager = $this->account->getServiceManager();

        // Load the Module Service
        $moduleService = $serviceManager->get(ModuleServiceFactory::class);

        $module = $moduleService->getByName($params['moduleName'], $this->account->getAccountId());
        return $this->sendOutput($module->toArray());
    }

    /**
     * PUT pass-through for save
     */
    public function putSaveAction()
    {
        return $this->postSaveAction();
    }

    /**
     * Save the module
     */
    public function postSaveAction()
    {
        $rawBody = $this->getRequest()->getBody();

        $ret = [];
        if (!$rawBody) {
            return $this->sendOutput(["error" => "Request input is not valid"]);
        }

        // Decode the json structure
        $objData = json_decode($rawBody, true);

        if (!isset($objData['name'])) {
            return $this->sendOutput(["error" => "name is a required param"]);
        }

        // Get the service manager of the current user
        $serviceManager = $this->account->getServiceManager();
        $moduleService = $serviceManager->get(ModuleServiceFactory::class);

        $module = $moduleService->createNewModule();

        if (isset($objData["id"]) && $objData["id"]) {
            $module->setId($objData["id"]);
        }

        $module->fromArray($objData);
        $module->setDirty(true);

        if ($moduleService->save($module, $this->account->getAccountId())) {
            // Return the saved module
            return $this->sendOutput($module->toArray());
        } else {
            return $this->sendOutput(["error" => "Error saving the module"]);
        }
    }

    /**
     * PUT pass-through for delete
     */
    public function putDeleteAction()
    {
        return $this->postDeleteAction();
    }

    /**
     * Delete the module
     */
    public function postDeleteAction()
    {
        $id = $this->request->getParam("id");
        if (!$id) {
            return $this->sendOutput(["error" => "id is a required param"]);
        }

        // Get the service manager of the current user
        $serviceManager = $this->account->getServiceManager();
        $moduleService = $serviceManager->get(ModuleServiceFactory::class);

        $module = $moduleService->getById($id, $this->account->getAccountId());

        if ($moduleService->delete($module)) {
            // Return true since we have successfully deleted the module
            return $this->sendOutput(true);
        } else {
            return $this->sendOutput(["error" => "Error while trying to delete the module"]);
        }
    }

    /**
     * Get the available module of an account
     */
    public function getGetAvailableModulesAction()
    {
        // Get the service manager of the current user
        $serviceManager = $this->account->getServiceManager();

        // Load the Module Service
        $moduleService = $serviceManager->get(ModuleServiceFactory::class);

        // Get the current user
        $user = $this->account->getUser();

        // Get the available modules for the current user
        $userModules = $moduleService->getForUser($user);

        $modules = [];

        // Loop through each module for the current user
        foreach ($userModules as $module) {
            // Convert the Module object into an array
            $modules[] = $module->toArray();
        }

        return $this->sendOutput($modules);
    }
}
