<?php

/**
 * Router handles loading a controller from a URL route
 */

// Setup autoloader
include(__DIR__ . "/../vendor/autoload.php");

use Netric\Application\Application;
use Aereus\Config\ConfigLoader;

// Set headers to allow CORS since we are using /svr resources in multiple clients
// @see http://www.html5rocks.com/en/tutorials/cors/#toc-adding-cors-support-to-the-server
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Authentication, Options, Content-Type, X-NTRC-ACCOUNT");


$configLoader = new ConfigLoader();
$applicationEnvironment = (getenv('APPLICATION_ENV')) ? getenv('APPLICATION_ENV') : "production";

// Setup the new config
$config = $configLoader->fromFolder(__DIR__ . "/../config", $applicationEnvironment);

// Run the application
Application::init($config)->run();
