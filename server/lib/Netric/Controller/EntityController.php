<?php
/**
 * This is just a simple test controller
 */
namespace Netric\Controller;

use \Netric\Mvc;

class EntityController extends Mvc\AbstractController
{
	public function test($params=array())
	{
        return $this->sendOutput("test");
	}

	/**
	 * Get the definition of an entity
     *
     * @param array $params Associative array of request params
	 */
	public function getDefinition($params=array())
	{
		if (!$params['obj_type'])
		{
			return $this->sendOutput(array("error"=>"obj_type is a required param"));
		}

		// Get the service manager and current user
		$serviceManager = $this->account->getServiceManager();
		$user = $this->account->getUser();

		// Load the entity definition
		$defLoader = $serviceManager->get("EntityDefinitionLoader");
		$def = $defLoader->get($params['obj_type']);
		if (!$def) 
		{
			return $this->sendOutput(array("error"=>$params['obj_type'] . " could not be loaded"));
		}

		$ret = $def->toArray();

		// TODO: As soon as views are included in EntityDefinition then update here
		$ret['views'] = array();

		// TODO: Get browser mode preference (Netric/Entity/ObjectType/User has no getSetting)
		/*
		$browserMode = $user->getSetting("/objects/browse/mode/" . $params['obj_type']);
		// Set default view modes
		if (!$browserMode) 
		{
			switch ($params['obj_type'])
			{
			case 'email_thread':
			case 'note':
				$browserMode = "previewV";
				break;
			default:
				$browserMode = "table";
				break;
			}
		}
		$ret["browser_mode"] = $browserMode;
		*/
		$ret["browser_mode"] = "table";

		// TODO: Get browser blank content

		// Get forms
		$entityForms = $serviceManager->get("Netric/Entity/Forms");
		$ret['forms'] = $entityForms->getDeviceForms($def, $user);

        return $this->sendOutput($ret);
	}

	/**
	 * Query entities
     *
     * @param array $params Associative array of request params
	 */
	public function query($params=array())
	{
        $ret = array();
        
        if (!isset($params["obj_type"]))
            return $this->sendOutput(array("error"=>"obj_type must be set"));
        
        $index = $this->account->getServiceManager()->get("EntityQuery_Index");
        
        $query = new \Netric\EntityQuery($params["obj_type"]);

        if(isset($params['offset']))
            $query->setOffset($params['offset']);
            
        if(isset($params['limit']))
            $query->setLimit($params["limit"]);
        
        // Parse values passed from POST or GET params
        \Netric\EntityQuery\FormParser::buildQuery($query, $params);

        /*
        // Check for private
        if ($olist->obj->isPrivate())
        {
            if ($olist->obj->def->getField("owner"))
                $olist->fields("and", "owner", "is_equal", $this->user->id);
            if ($olist->obj->def->getField("owner_id"))
                $olist->addCondition("and", "owner_id", "is_equal", $this->user->id);
            if ($olist->obj->def->getField("user_id"))
                $olist->addCondition("and", "user_id", "is_equal", $this->user->id);
        }
        */

        // Execute the query
        $res = $index->executeQuery($query);
        
        // Pagination
        // ---------------------------------------------
        $ret["total_num"] = $res->getTotalNum();
        $ret["offset"] = $res->getOffset();
        $ret["limit"] = $query->getLimit();

        /*
         * This may no longer be needed with the new client
         * - Sky Stebnicki
        if ($res->getTotalNum() > $query->getLimit())
        {
            $prev = -1; // Hide

            // Get total number of pages
            $leftover = $res->getTotalNum() % $query->getLimit();
            
            if ($leftover)
                $numpages = (($res->getTotalNum() - $leftover) / $query->getLimit()) + 1; //($numpages - $leftover) + 1;
            else
                $numpages = $res->getTotalNum() / $query->getLimit();
            // Get current page
            if ($offset > 0)
            {
                $curr = $offset / $query->getLimit();
                $leftover = $offset % $query->getLimit();
                if ($leftover)
                    $curr = ($curr - $leftover) + 1;
                else 
                    $curr += 1;
            }
            else
                $curr = 1;
            // Get previous page
            if ($curr > 1)
                $prev = $offset - $query->getLimit();
            // Get next page
            if (($offset + $query->getLimit()) < $res->getTotalNum())
                $next = $offset + $query->getLimit();
            $pag_str = "Page $curr of $numpages";

            $ret['paginate'] = array();
            $ret['paginate']['nextPage'] = $next;
            $ret['paginate']['prevPage'] = $prev;
            $ret['paginate']['desc'] = $pag_str;
        }
        */
        
        // Set results
        $entities = array();
        for ($i = 0; $i < $res->getNum(); $i++)
        {
            $ent = $res->getEntity($i);
            
            if(isset($params['updatemode']) && $params['updatemode']) // Only get id and revision
			{
                // Return condensed results
                $entities[] = array(
                    "id" => $ent->getId(),
                    "revision" => $ent->getValue("revision"),
                    "num_comments" => $ent->getValue("num_comments"),
                );
			}
			else
			{
                // TODO: security
                
                // Print full details
                $entities[] = $ent->toArray();
            }
        }
        $ret["entities"] = $entities;
        
        return $this->sendOutput($ret);
	}

    /**
     * Retrieve a single entity
     *
     * @param array $params Associative array of request params
     */
    public function get($params=array())
    {
        $ret = array();

        if (!$params['obj_type'] || !$params['id'])
        {
            return $this->sendOutput(array("error"=>"obj_type and id are required params"));
        }


        $loader = $this->account->getServiceManager()->get("EntityLoader");
        $entity = $loader->get($params['obj_type'], $params['id']);

        // TODO: Check permissions

        $ret = $entity->toArray();
        
        // Check for definition (request may be made by client)
        if (isset($params['loadDef']))
        {
            // TODO: add $ret['definition'] with results from $this->getDefinition
        }


        return $this->sendOutput($ret);
    }

    /**
     * Save an entity
     *
     * @param array $params Associative array of request params
     */
    public function save($params=array())
    {
        $ret = array();
        if (!isset($params['raw_body']))
        {
            return $this->sendOutput(array("error"=>"Request input is not valid"));
        }

        // Decode the json structure
        $objData = json_decode($params['raw_body'], true);

        if (!isset($objData['obj_type']))
        {
            return $this->sendOutput(array("error"=>"obj_type is a required param"));
        }

        $loader = $this->account->getServiceManager()->get("EntityLoader");

        if (isset($objData['id']))
        {
            $entity = $loader->get($objData['obj_type'], $objData['id']);
        }
        else
        {
            $entity = $loader->create($objData['obj_type']);
        }

        // Parse the params
        $entity->fromArray($objData);

        // Save the entity
        $dataMapper = $this->account->getServiceManager()->get("Entity_DataMapper");
        if ($dataMapper->save($entity))
        {
            return $this->sendOutput($entity->toArray());
        }
        else
        {
            return $this->sendOutput(array("error"=>"Error saving: " . $dataMapper->getLastError()));
        }
    }
}
