<?php

/**
 * Utility class
 */
class Utilities {
	
	// Supported file types
	private $supportedCsvFileTypes = array('csv');
	
	// Supported file mime types
	private $supportedCsvMimes = array('application/vnd.ms-excel','text/plain','text/csv','text/tsv');
	
	public function validateCsvFileType($file, $saved = false) {
		// Get file extension
		$base = $saved === false ? $file['name'] : basename($file);
		$temp = explode('.', $base);
		$extension = end($temp);
		
		// Get mime type
		$path = $saved === false ? $file['tmp_name'] : $file;
		$fi = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($fi, $path);
		finfo_close($fi);
		
		// Validate extension and mime type
		$validated = true;
		if (!in_array($extension, $this->supportedCsvFileTypes)) {
			$validated = false;
		} else if (!in_array($mime, $this->supportedCsvMimes)) {
			$validated = false;
		}
		
		return $validated;
	}
	
}