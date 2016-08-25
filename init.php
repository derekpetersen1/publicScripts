<?php

// Begin session
session_start();

// EDIT
//<!--
$path = '/absolute/filepath/to/folder/containing/publicScripts';
$siteUrl = 'http://localhost';
// -->

// DO NOT EDIT
// <!--
// Define constants
define('ABS_PATH', "$path/publicScripts");
define('WEB_PATH', $siteUrl);
define('FILE_PATH', ABS_PATH.'/files');

// Initialize autoloader
require_once("classes/Autoloader.php");
spl_autoload_register("Autoloader::loader");
// -->

?>