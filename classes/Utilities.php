<?php

/**
 * Utility class
 */
class Utilities {
	
	// Supported file types
	private $supportedCsvFileTypes = array('csv','txt');
	
	/**
	 * Utility to validate an uploaded csv file
	 *
	 * @param array $file
	 * @param bool $saved
	 *
	 * @returns bool $validated
	 */
	public function validateCsvFileType($file, $saved = false) {
		$validated = true;
		
		// Get file extension
		$base = $saved === false ? $file['name'] : basename($file);
		$temp = explode('.', $base);
		$extension = end($temp);
		
		// Validate extension
		if (!in_array($extension, $this->supportedCsvFileTypes)) {
			$validated = false;
		}
		
		// Check file contents
		if (!$handle = fopen($file['tmp_name'], 'r')) {
			$validated = false;
		} else {
			$csvContentCheck = fgetcsv($handle);
			
			if ($csvContentCheck === false || $csvContentCheck === null) {
				$validated = false;
			}
		}
		
		return $validated;
	}
	
}