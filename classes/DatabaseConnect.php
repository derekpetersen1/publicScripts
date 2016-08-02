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
		
		$db = "database";
		
		try {
			$con = new PDO('mysql:host=localhost;dbname='.$db, 'root', 'root');
			$con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (Exception $e) {
			$con = null;
		}
		
		return $con;
	}
	
}


?>