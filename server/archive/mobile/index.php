<?php
	require_once(__DIR__ . "/../src/AntLegacy/AntConfig.php");
	require_once("ant.php");
	require_once("ant_user.php");
	
	// ant_user above will validate to make sure user is logged in
	header("Location: /mobile/main.php");
?>
