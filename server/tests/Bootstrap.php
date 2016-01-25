<?php
namespace NetricTest;

// Get application autoloader
include("../init_autoloader.php");

use Zend\Loader\StandardAutoloader;
use RuntimeException;
use Netric;
use Netric\Entity\ObjType\UserEntity;

error_reporting(E_ALL | E_STRICT);
chdir(__DIR__);

/**
 * Test bootstrap, for setting up autoloading
 */
class Bootstrap
{
    protected static $account;

    public static function init()
    {
        static::initAutoloader();

        // Initialize Netric Application and Account
        // ------------------------------------------------
        $config = new \Netric\Config();

        // Initialize application
        $application = new \Netric\Application\Application($config);

        // Initialize account
        static::$account = $application->getAccount();

        // Set the current user to administrator so permissions are not limiting
        $loader = static::$account->getServiceManager()->get("EntityLoader");
        $user = $loader->get("user", UserEntity::USER_ADMINISTRATOR);
        static::$account->setCurrentUser($user);
    }

    public static function getAccount()
    {
        return static::$account;
    }

    protected static function initAutoloader()
    {
            
        $autoLoader = new StandardAutoloader(array(
            /*
            'prefixes' => array(
                'MyVendor' => __DIR__ . '/MyVendor',
            ),
            */
            'namespaces' => array(
                __NAMESPACE__ => __DIR__ . '/' . __NAMESPACE__,
            ),
            'fallback_autoloader' => true,
        ));
        $autoLoader->register();

    }

    protected static function findParentPath($path)
    {
        $dir = __DIR__;
        $previousDir = '.';
        while (!is_dir($dir . '/' . $path)) {
            $dir = dirname($dir);
            if ($previousDir === $dir) return false;
            $previousDir = $dir;
        }
        return $dir . '/' . $path;
    }
}

Bootstrap::init();