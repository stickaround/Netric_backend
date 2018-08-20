<?php
/**
 * Cleanup objects and performan periodic maintenance
 *
 * @category	Ant
 * @package		Email
 * @subpackage	Queue_Process
 * @copyright	Copyright (c) 2003-2012 Aereus Corporation (http://www.aereus.com)
 */
require_once(dirname(__FILE__)."/../../lib/AntConfig.php");
require_once("src/AntLegacy/Ant.php");
require_once("src/AntLegacy/AntService.php");
require_once("src/AntLegacy/AntRoutine.php");
require_once("services/ObjectMaint.php");

ini_set("memory_limit", "-1");	

$svc = new ObjectMaint();
$svc->run();
echo "Finished!\n";