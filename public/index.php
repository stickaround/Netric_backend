<?php
include(__DIR__ . "/../src/AntLegacy/AntConfig.php");

// Handle desktop redirection
// -------------------------------------------------------------------------------
$get = "";

// For login errors
if ($_GET['e'])
	$get .= ($get) ? "&e=".$_GET['e'] : "?e=".$_GET['e'];

// For login errors
if ($_GET['p'])
	$get .= ($get) ? "&p=".$_GET['p'] : "?p=".$_GET['p'];
	

header("Location: /login" . $get);