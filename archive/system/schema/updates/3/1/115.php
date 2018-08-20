<?php
/**
 * Clear theme cache
 *
 * $ant is a global variable to this script and is already created by the calling class
 */
require_once("src/AntLegacy/CDatabase.awp");
require_once("src/AntLegacy/CAntObject.php");
require_once("src/AntLegacy/Ant.php");
require_once("src/AntLegacy/AntUser.php");
require_once("src/AntLegacy/Dacl.php");

if (!$ant)
	die("Update failed because $ ant is not defined");

$dbh = $ant->dbh;

$result = $dbh->Query("SELECT id FROM users WHERE id<'0';");
$num = $dbh->GetNumberRows($result);
for ($i = 0; $i < $num; $i++)
{
	$uid = $dbh->GetValue($result, 0, "id");
	$cache = CCache::getInstance();
	$cache->remove($dbh->dbname."/users/".$uid."/theme");
}