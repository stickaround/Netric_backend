<?php
/**
 * Our implementation of a ServiceLocator pattern
 * 
 * @author Sky Stebnicki <sky.stebnicki@aereus.com>
 * @copyright 2015 Aereus
 */
namespace Netric\ServiceManager;

use Netric\Account\Account;
use Netric;

/**
 * Class for constructing, caching, and finding services by name
 */
class ServiceManager extends AbstractServiceManager implements AccountServiceManagerInterface
{
    /**
	 * Handle to netric account
	 * 
	 * @var Account
	 */
	private $account = null;

    /**
     * Map a name to a class factory
     *
     * The target will be appended with 'Factory' so
     * "test" => "Netric/ServiceManager/Test/Service",
     * will load
     * Netric/ServiceManager/Test/ServiceFactory
     *
     * Use these sparingly because it does obfuscate from the
     * client what classes are being loaded.
     *
     * @var array
     */
    protected $invokableFactoryMaps = array(
        // Test service map
        "test" => "Netric/ServiceManager/Test/Service",
        // The entity factory service will initialize new entities with injected dependencies
        "EntityFactory" => "Netric/Entity/EntityFactory",
        // The service required for saving recurring patterns
        "RecurrenceDataMapper" => "Netric/Entity/Recurrence/RecurrenceDataMapper",
        // IdentityMapper for loading/saving/caching RecurrencePatterns
        "RecurrenceIdentityMapper" => "Netric/Entity/Recurrence/RecurrenceIdentityMapper",
    );

    /**
	 * Class constructor
	 *
	 * We are private because the class must be a singleton to assure resources
	 * are initialized only once.
	 *
	 * @param Account $account The account we are loading services for
	 */
	public function __construct(Account $account)
	{
		$this->account = $account;
        parent::__construct($account->getApplication());
	}

	/**
	 * Get account instance of netric
	 *
	 * @return Account
	 */
	public function getAccount()
	{
		return $this->account;
	}

	/**
	 * Get a service by name
	 *
	 * @param string $serviceName
	 * @return mixed The service object and false on failure
	 */
	public function get($serviceName)
	{
		$service = false;

		/*
		 * First check to see if we have a local factory function to load the service.
		 * This is the legacy way of loading services and this first if clause will
		 * eventually go away and just leave the 'else' code below.
		 */
		if (method_exists($this, "factory" . $serviceName))
        {
        	// Return cached version if already loaded
			if ($this->isLoaded($serviceName))
				return $this->loadedServices[$serviceName];

            $service = call_user_func(array($this, "factory" . $serviceName));

            // Cache the service
            if ($service)
            {
                $this->loadedServices[$serviceName] = $service;
            }
            else
            {
                throw new Exception\RuntimeException(sprintf(
                    '%s: A local factory function was found for "%s" but it did not return a valid service.',
                    get_class($this) . '::' . __FUNCTION__,
                    $serviceName
                ));
            }
        }
        else
        {
            $service = parent::get($serviceName);
        }

		return $service;
	}

    /*
     * TODO: All of the below factories need to be moved to actual factory classes
     * ===========================================================================
     */

	/**
	 * Construct datamapper for an object type
	 *
	 * @param string $objType
	 * @return DataMapper
	 */ 
	private function factoryEntity_DataMapper()
    {
		// For now all we support is pgsql
		$dm = new \Netric\Entity\DataMapper\Pgsql($this->getAccount(), $this->get("Db"));
		return $dm;
	}

    /**
	 * Construct and get handle to account database
	 *
	 * @return Netric\Db\DbInterface
	 */
	private function factoryDb()
	{
        
        // Setup antsystem datamapper
        $config = $this->get("Config");
        $db = new \Netric\Db\Pgsql($config->db["host"], $this->getAccount()->getDatabaseName(), $config->db["user"], $config->db["password"]);
        $db->setSchema("acc_" . $this->getAccount()->getId());
		return $db;
	}
    
	/**
	 * Construct datamapper for an object type definition
	 *
	 * @return EntityDefinition_DataMapper
	 */
	private function factoryEntityDefinition_DataMapper()
	{
		// For now all we support is pgsql
		$dm = new \Netric\EntityDefinition\DataMapper\Pgsql($this->getAccount(), $this->get("Db"));
		return $dm;
	}

    /**
	 * Construct entity definition loader
	 *
	 * @return EntityDefinitionLoader
	 */
	private function factoryEntityDefinitionLoader()
	{
		// For now all we support is pgsql
		$dm = $this->get("EntityDefinition_DataMapper");
        $cache = $this->get("Cache");
		$loader = new \Netric\EntityDefinitionLoader($dm, $cache);
		return $loader;
	}

	/**
	 * Get config service
	 *
	 * @return AntConfig
	 */
	private function factoryConfig()
	{
		return $this->getAccount()->getApplication()->getConfig();
	}
    
    /**
	 * Get cache
	 *
	 * @return Netric\Cache\CacheInterface
	 */
	private function factoryCache()
	{
        return $this->getAccount()->getApplication()->getCache();
	}

	/**
	 * Get entity loader
	 *
	 * @return EntityLoader
	 */
	private function factoryEntityLoader()
	{
		$dm = $this->get("Entity_DataMapper");
		$definitionLoader = $this->get("EntityDefinitionLoader");

		$loader = new \Netric\EntityLoader($dm, $definitionLoader);
		return $loader;
	}

	/**
	 * Get entity sync service
	 *
	 * @return Netric\EntitySync\EntitySync
	 */
	private function factoryEntitySync()
	{
		$dm = $this->get("EntitySync_DataMapper");
		$manager = new \Netric\EntitySync\EntitySync($dm);
		return $manager;
	}

	/**
	 * Get entity commit manager
	 *
	 * @return EntityLoader
	 */
	private function factoryEntitySyncCommitManager()
	{
		$dm = $this->get("EntitySyncCommit_DataMapper");
		$manager = new \Netric\EntitySync\Commit\CommitManager($dm);
		return $manager;
	}

	/**
	 * Get entity commit datamapper
	 *
	 * @return EntityLoader
	 */
	private function factoryEntitySyncCommit_DataMapper()
	{
		$dm = new \Netric\EntitySync\Commit\DataMapper\Pgsql($this->getAccount());
		return $dm;
	}

	/**
	 * Get entity commit datamapper
	 *
	 * @return EntityLoader
	 */
	private function factoryEntitySync_DataMapper()
	{
		$db = $this->get("Db");
		$dm = new \Netric\EntitySync\DataMapperPgsql($this->getAccount(), $db);
		return $dm;
	}
    
    /**
	 * Get entity loader
	 *
	 * @return EntityLoader
	 */
	private function factoryEntityGroupings_Loader()
	{
		$dm = $this->get("Entity_DataMapper");
        $cache = $this->get("Cache");
		$loader = new \Netric\EntityGroupings\Loader($dm, $cache);
		return $loader;
	}
    
    /**
	 * Get the logger
	 *
	 * @return Log
	 */
	private function factoryLog()
	{
        return $this->getAccount()->getApplication()->getLog();
	}
    
    /**
	 * Get entity query index
	 *
	 * @return Netric\EntityQuery\IndexInterface
	 */
	private function factoryEntityQuery_Index()
	{
        return new \Netric\EntityQuery\Index\Pgsql($this->getAccount());
	}
    
     /**
	 * Get entity query index
	 *
	 * @return Netric\EntityQuery\IndexInterface
	 */
	private function factoryEntity_RecurrenceDataMapper()
	{
		$acct = $this->getAccount();
		$dbh = $this->get("Db");
        return new \Netric\Entity\Recurrence\RecurrenceDataMapper($acct, $dbh);
	}

    /**
     * Get the application datamapper
     *
     * @return Netric\Application\DataMapperInterface
     */
    private function factoryApplication_DataMapper()
    {
        $config = $this->get("Config");
        return new \Netric\Application\DataMapperPgsql($config->db["host"],
                                                $config->db["sysdb"],
                                                $config->db["user"],
                                                $config->db["password"]);
    }

	/**
	 * Get DACL loader for security
	 *
	 * @return DaclLoader
	 */
	private function factoryDaclLoader()
	{
		return DaclLoader::getInstance($this->ant->dbh);
	}
    
    /**
	 * Get AntFs class
     * 
     * @deprecated This is legacy code used only for the entity datamapper at this point
	 *
	 * @return \AntFs 
	 */
	private function factoryAntFs()
	{
        require_once(dirname(__FILE__) . "/../../AntConfig.php");
        require_once(dirname(__FILE__) . "/../../CDatabase.awp");
        require_once(dirname(__FILE__) . "/../../Ant.php");
        require_once(dirname(__FILE__) . "/../../AntUser.php");
        require_once(dirname(__FILE__) . "/../../AntFs.php");

        $ant = new \Ant($this->getAccount()->getId());
        $user = $this->getAccount()->getUser();
        if (!$user)
            $user = $this->getAccount()->getUser(\Netric\UserEntity::USER_ANONYMOUS);
        $user = new \AntUser($ant->dbh, $user->getId(), $ant);
        $antfs = new \AntFs($ant->dbh, $user);
        
		return $antfs;
	}

	/**
	 * Get Help class
	 *
	 * @return Help 
	 */
	private function factoryHelp()
	{
		return new Help();
	}
}
