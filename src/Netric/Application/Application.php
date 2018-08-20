<?php
namespace Netric\Application;

use Netric\Account\Account;
use Netric\Application\Exception;
use Netric\Application\Response\ResponseInterface;
use Netric\Application\Setup\Setup;
use Netric\Request\RequestInterface;
use Netric\Mvc\Router;
use Netric\Config\Config;
use Netric\Log\LogInterface;
use Netric\Log\Log;
use Netric\Cache\MemcachedCache;
use Netric\Cache\CacheInterface;
use Netric\Account\AccountIdentityMapper;
use Netric\ServiceManager\ApplicationServiceManager;
use Netric\Entity\DataMapperInterface;
use Netric\Stats\StatsPublisher;
use Netric\Request\RequestFactory;

/**
 * Main application instance class
 */
class Application
{
    /**
     * Initialized configuration class
     *
     * @var Config
     */
    protected $config = null;

    /**
     * Application log
     *
     * We make it static so that it is not re-initialized any time an application instance
     * is loaded. This is especially useful when we want to mock the log out in unit tests
     * and make sure that all loaded instances of the Application inherit the mocked log.
     *
     * @var LogInterface
     */
    static protected $log = null;

    /**
     * Application DataMapper
     *
     * @var DataMapperInterface
     */
    private $dm = null;

    /**
     * Application cache
     *
     * @var CacheInterface
     */
    private $cache = null;

    /**
     * Accounts identity mapper
     *
     * @var AccountIdentityMapper
     */
    private $accountsIdentityMapper = null;

    /**
     * Request made when launching the application
     *
     * @var RequestInterface
     */
    private $request = null;

    /**
     * Application service manager
     *
     * @var ApplicationServiceManager
     */
    private $serviceManager = null;

    /**
     * The unique ID of this request
     *
     * @var string
     */
    private $requestId = null;

    /**
     * Initialize application
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        // start profiling if enabled
        if (extension_loaded('xhprof')) {
            xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);
        }

        $this->config = $config;

        // Setup log
        if (!self::$log) {
            self::$log = new Log($config->log);
        }

        // Watch for error notices and log them
        set_error_handler(array(self::$log, "phpErrorHandler"));

        // Log unhandled exceptions
        //set_exception_handler(array(self::$log, "phpUnhandledExceptionHandler"));

        // Watch for fatal errors which cause script execution to fail
        //register_shutdown_function(array(self::$log, "phpShutdownErrorChecker"));

        // Setup the application service manager
        $this->serviceManager = new ApplicationServiceManager($this);

        // Setup application datamapper
        $this->dm = $this->serviceManager->get(DataMapperFactory::class);

        // TODO: Convert the below to service factories

        // Setup application cache
        $this->cache = new MemcachedCache($config->cache);

        // Setup account identity mapper
        $this->accountsIdentityMapper = new AccountIdentityMapper($this->dm, $this->cache);
    }

    /**
     * Initialize an instance of the application
     *
     * @param Config $config
     * @return Application
     */
    public static function init(Config $config)
    {
        return new Application($config);
    }

    /**
     * Run The application
     *
     * @param string $path Optional initial route to load
     * @return int Return status code
     */
    public function run($path = "") : int
    {
        $returnStatusCode = 0;

        // We give each request a unique ID in order to track calls and logs through the system
        $this->requestId = uniqid();

        // Add to every log to make tracking down problems easier
        self::$log->setRequestId($this->requestId);

        // Get the request
        $request = $this->serviceManager->get(RequestFactory::class);

        // Get the router
        $router = new Router($this);

        // Check if we have set the first/initial route
        if ($path) {
            $request->setPath($path);
        }

        // Execute through the router
        try {
            $response = $router->run($request);
            // Fail the run if the response code is not successful
            if ($response instanceof ResponseInterface) {
                $returnStatusCode = $response->getReturnCode();
            }
        } catch (\Exception $unhandledException) {
            // An exception took place and was not handled
            $this->getLog()->error(
                'Unhandled application exception in ' .
                $unhandledException->getFile() .
                ':' . $unhandledException->getLine() .
                "; message=" . $unhandledException->getMessage() .
                "\n" . $unhandledException->getTraceAsString()
            );

            // If we are suppressing logs then print out this exception
            //if ($this->config->log->writer == 'null') {
                print(
                    $this->config->log->writer . "\n" .
                    'Unhandled application exception in ' .
                    $unhandledException->getFile() .
                    ':' . $unhandledException->getLine() .
                    "; message=" . $unhandledException->getMessage() .
                    "\n" . $unhandledException->getTraceAsString()
                );
            //}

            $returnStatusCode = -1;
        }

        // Handle any profiling needed for this request
        $this->profileRequest();

        return $returnStatusCode;
    }

    /**
     * Get initialized config
     *
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Get the unique ID of this request
     *
     * @return string
     */
    public function getRequestId()
    {
        return $this->requestId;
    }

    /**
     * Get current account
     *
     * @param string $accountId If set the pull an account by id, otherwise automatically get from url or config
     * @param string $accountName If set try to get an account by the unique name
     * @throws \Exception when an invalid account id or name is passed
     * @return Account
     */
    public function getAccount($accountId = "", $accountName = "")
    {
        // If no specific account is set to be loaded, then get current/default
        if (!$accountId && !$accountName) {
            $accountName = $this->getAccountName();
        }

        if (!$accountId && !$accountName) {
            throw new \Exception("Cannot get account without accountName");
        }

        // Get the account with either $accountId or $accountName
        if ($accountId) {
            return $this->accountsIdentityMapper->loadById($accountId, $this);
        }
        
        return $this->accountsIdentityMapper->loadByName($accountName, $this);
    }

    /**
     * Get all acounts for this application
     *
     * @return Account[]
     */
    public function getAccounts()
    {
        $config = $this->getConfig();
        $accountsData = $this->dm->getAccounts($config->version);

        $accounts = [];
        foreach ($accountsData as $data) {
            $accounts[] = $this->accountsIdentityMapper->loadById($data['id'], $this);
        }

        return $accounts;
    }


    /**
     * Get account and username from email address
     *
     * @param string $emailAddress The email address to pull from
     * @return array("account"=>"accountname", "username"=>"the login username")
     */
    public function getAccountsByEmail($emailAddress)
    {
        $accounts = $this->dm->getAccountsByEmail($emailAddress);

        // Add instanceUri
        for ($i = 0; $i < count($accounts); $i++) {
            $proto = ($this->config->use_https) ? "https://" : "http://";
            $accounts[$i]['instanceUri'] = $proto . $accounts[$i]["account"] . "." . $this->config->localhost_root;
        }

        return $accounts;
    }

    /**
     * Set account and username from email address
     *
     * @param int $accountId The id of the account user is interacting with
     * @param string $username The user name - unique to the account
     * @param string $emailAddress The email address to pull from
     * @return bool true on success, false on failure
     */
    public function setAccountUserEmail($accountId, $username, $emailAddress)
    {
        return $this->dm->setAccountUserEmail($accountId, $username, $emailAddress);
    }

    /**
     * Determine what account we are working with.
     *
     * This is usually done by the third level url, but can fall
     * all the way back to the system default account if needed.
     *
     * @return string The unique account name for this instance of netric
     */
    private function getAccountName()
    {
        global $_SERVER, $_GET, $_POST, $_SERVER;

        $ret = null;

        // Check url - 3rd level domain is the account name
        if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] != $this->getConfig()->localhost_root
            && strpos($_SERVER['HTTP_HOST'], "." . $this->getConfig()->localhost_root)) {
            $left = str_replace("." . $this->getConfig()->localhost_root, '', $_SERVER['HTTP_HOST']);
            if ($left) {
                return $left;
            }
        }

        // Check get - less common
        if (isset($_GET['account']) && $_GET['account']) {
            return $_GET['account'];
        }

        // Check post - less common
        if (isset($_POST['account']) && $_POST['account']) {
            return $_POST['account'];
        }

        // Check for any third level domain (not sure if this is safe)
        if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] && substr_count($_SERVER['HTTP_HOST'], '.') >= 2) {
            $left = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], '.'));
            if ($left) {
                return $left;
            }
        }

        // Get default account from the system settings
        return $this->getConfig()->default_account;
    }

    /**
     * Initialize a brand new account and create the admin user
     *
     * @param string $accountName A unique name for the new account
     * @param string $adminUserName Required username for the admin/first user
     * @param string $adminUserPassword Required password for the admin
     * @return Account
     */
    public function createAccount($accountName, $adminUserName, $adminUserPassword)
    {
        // Make sure the account does not already exists
        if ($this->accountsIdentityMapper->loadByName($accountName, $this)) {
            throw new Exception\AccountAlreadyExistsException($accountName . " already exists");
        }

        // TODO: Check the account name is valid

        // Create new account
        $accountId = $this->accountsIdentityMapper->createAccount($accountName);

        // Make sure the created account is valid
        if (!$accountId) {
            throw new Exception\CouldNotCreateAccountException(
                "Failed creating account " . $this->accountsIdentityMapper->getLastError()->getMessage()
            );
        }

        // Load the newly created account
        $account = $this->accountsIdentityMapper->loadById($accountId, $this);

        // Initialize with setup
        $setup = new Setup();
        $setup->setupAccount($account, $adminUserName, $adminUserPassword);

        // If the username is an email address then set the email address to be the username
        if (strpos($adminUserName, '@') !== false) {
            $this->setAccountUserEmail($accountId, $adminUserName, $adminUserName);
        }

        // Return the new account
        return $account;
    }

    /**
     * Delete an account by name
     *
     * @param string $accountName The unique name of the account to delete
     * @return bool on success, false on failure
     */
    public function deleteAccount($accountName)
    {
        // Get account by name
        $account = $this->getAccount(null, $accountName);

        // Delete the account if it is valid
        if ($account->getId()) {
            return $this->accountsIdentityMapper->deleteAccount($account);
        }

        return false;
    }

    /**
     * Create the application database if it does not exist
     *
     * @param int $numRetries If > 0 then retry after 1 second for each iteration
     */
    public function initDb($numRetries = 0)
    {
        // Create database if it does not exist
        try {
            // Failures will result in a \RuntimeException, otherwise assume success
            $this->dm->createDatabase();
        } catch (\RuntimeException $ex) {
            // If we are set to retry on failure, then wait a second and try again
            if ($numRetries > 0) {
                $this->getLog()->info("Could not create the system database, waiting a second to try again");
                sleep(1);
                return $this->initDb(--$numRetries);
            } else {
                // Let the caller know that we cannot create the database
                $exceptionText = "Could not create application database: ";
                if ($this->dm->getLastError()) {
                    $exceptionText .= $this->dm->getLastError()->getMessage();
                }
                throw new \RuntimeException($exceptionText);
            }
        }

        // Initialize with setup
        $setup = new Setup();
        return $setup->updateApplication($this);
    }

    /**
     * Get the application service manager
     *
     * @return ApplicationServiceManager
     */
    public function getServiceManager()
    {
        return $this->serviceManager;
    }

    /**
     * Get the application log
     *
     * @return \Netric\Log\Log
     */
    public function getLog()
    {
        return self::$log;
    }

    /**
     * Get the application cache
     *
     * @return \Netric\Cache\CacheInterface
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * Get the request for this application
     *
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Create a new email domain
     *
     * @param int $accountId
     * @param string $domainName
     * @return bool true on success, false on failure
     */
    public function createEmailDomain($accountId, $domainName)
    {
        return $this->dm->createEmailDomain($accountId, $domainName);
    }

    /**
     * Delete an existing email domain
     *
     * @param int $accountId
     * @param string $domainName
     * @return bool true on success, false on failure
     */
    public function deleteEmailDomain($accountId, $domainName)
    {
        return $this->dm->deleteEmailDomain($accountId, $domainName);
    }

    /**
     * Create or update an email alias
     *
     * @param int $accountId
     * @param string $emailAddress
     * @param string $goto
     * @return bool true on success, false on failure
     */
    public function createOrUpdateEmailAlias($accountId, $emailAddress, $goto)
    {
        return $this->dm->createOrUpdateEmailAlias($accountId, $emailAddress, $goto);
    }

    /**
     * Delete an email alias
     *
     * @param int $accountId
     * @param string $emailAddress
     * @return bool true on success, false on failure
     */
    public function deleteEmailAlias($accountId, $emailAddress)
    {
        return $this->dm->deleteEmailAlias($accountId, $emailAddress);
    }

    /**
     * Create a new or update an existing email user in the mail system
     *
     * @param int $accountId
     * @param string $emailAddress
     * @param string $password
     * @return bool true on success, false on failure
     */
    public function createOrUpdateEmailUser($accountId, $emailAddress, $password)
    {
        return $this->dm->createOrUpdateEmailUser($accountId, $emailAddress, $password);
    }

    /**
     * Delete an email user from the mail system
     *
     * @param int $accountId
     * @param string $emailAddress
     * @return bool true on success, false on failure
     */
    public function deleteEmailUser($accountId, $emailAddress)
    {
        return $this->dm->deleteEmailUser($accountId, $emailAddress);
    }

    /**
     * Obtain a lock so that only one instance of a process can run at once
     *
     * @param string $uniqueLockName Globally unique lock name
     * @param int $expiresInSeconds Expire after defaults to 1 day or 86400 seconds
     * @return bool true if lock obtained, false if the process name is already locked (running)
     */
    public function acquireLock($uniqueLockName, $expiresInSeconds = 86400)
    {
        return $this->dm->acquireLock($uniqueLockName, $expiresInSeconds);
    }

    /**
     * Clear a lock so that only one instance of a process can run at once
     *
     * @param string $uniqueLockName Globally unique lock name
     */
    public function releaseLock($uniqueLockName)
    {
        $this->dm->releaseLock($uniqueLockName);
    }

    /**
     * Refresh the lock to extend the expires timeout
     *
     * @param string $uniqueLockName Globally unique lock name
     * @return bool true on success, false on failure
     */
    public function extendLock($uniqueLockName)
    {
        return $this->dm->extendLock($uniqueLockName);
    }

    /**
     * Handle profiling this request if enabled
     */
    private function profileRequest()
    {
        if (!extension_loaded('xhprof') || !$this->config->profile->enabled) {
            return;
        }

        // Stop profiler and get data
        $xhprofData = xhprof_disable();

        // Loop through each function profiled
        foreach ($xhprofData as $functionAndCalledFrom => $stats) {
            // If the total walltime (duration) of the function is worth tracking then log
            if ((int)$stats['wt'] >= (int)$this->config->profile->min_wall) {
                $functionCalled = $functionAndCalledFrom;
                $calledFrom = "";

                /*
                 * xhprof puts the key in the following form: <calledFrom>==><class_function_called>
                 * unless it is the main wrapper entry for the entire page, the key name will
                 * just be main()
                 */
                if ($functionCalled !== 'main()') {
                    list($functionCalled, $calledFrom) = explode("==>", $functionAndCalledFrom);
                }

                $profileData = array(
                    "type" => "profile",
                    "function_name" => $functionCalled,
                    "called_from" => $calledFrom,
                    "num_calls" => $stats['ct'],
                    "duration" => $stats['wt'],
                    "cputime" => $stats['cpu'],
                    "memoryused" => $stats['mu'],
                    "peakmemoryused" => $stats['pmu'],
                );
                self::$log->warning($profileData);
            }
        }

        // Send total request time to StatsD in ms (wall time is in microseconds)
        if (isset($xhprofData['main()'])) {
            $statNamePath = 'route' . str_replace("/", ".", $_SERVER['REQUEST_URI']);

            StatsPublisher::timing($statNamePath . '.responsetime', round($xhprofData['main()']['wt'] * 1000));
            StatsPublisher::timing($statNamePath . '.memoryused', $xhprofData['main()']['mu']);
            StatsPublisher::increment($statNamePath . '.hits');

            // Just track all service calls
            StatsPublisher::timing('api.responsetime', round($xhprofData['main()']['wt'] * 1000));
            StatsPublisher::timing('api.memoryused', $xhprofData['main()']['mu']);
            StatsPublisher::increment('api.hits');
        }

        /*
         * TODO: Add a setting for saving the full profile dump in development environments
         * Then we can create a container in netric just for xhprof so that developers
         * can load up any request to see the full profile and determine where performance
         * issues might be taking place.
         */
        if ($this->config->profile->save_profile) {
            $file_name = __DIR__ . '/../../../data/profile_runs/' . $this->getRequestId() . '.netric.xhprof';
            $file = fopen($file_name, 'w');
            if ($file) {
                // Use PHP serialize function to store the XHProf's
                fwrite($file, serialize($xhprofData));
                fclose($file);
            }
        }
    }
}