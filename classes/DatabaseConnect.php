<?php

/**
 * Class that performs tasks related to the database
 */
class DatabaseConnect {
	
	/**
	 * Gets a PDO connection object
	 * @param string $db
	 * 
	 * @returns object $con
	 */
	public static function getConnection($db = null) {
		
		// EDIT
		// <!--
		$host = 'localhost';
		$db = 'database';
		$user = 'root';
		$pass = 'root';
		// -->
		
		// DO NOT EDIT
		// <!--
		try {
			$con = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
			$con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (Exception $e) {
			$con = null;
		}
		// -->
		
		return $con;
	}
	
}


?>