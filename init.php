<?php

// Begin session
session_start();

// Define constants
define('ABS_PATH', '/absolute/file/path/publicScripts');
define('WEB_PATH', 'http://yourpage.com');
define('FILE_PATH', '/absolute/path/to/files');

// Initialize autoloader
require_once("classes/Autoloader.php");
spl_autoload_register("Autoloader::loader");

?>