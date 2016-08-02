<?php

/**
 * Controller class for csv file imports
 */
class CsvImporter {
	
	// Default max csv file size is 10 Mb.
	private $maxSize = 10485760;
	
	// Supported table names must match what is in the database
	private $supportedTables = array('User', 'Weapon');
	
	// Supported file types
	private $supportedFileTypes = array('csv');
	
	// Supported file mime types
	private $supportedMimes = array('application/vnd.ms-excel','text/plain','text/csv','text/tsv');
	
	function __construct() {
		
	}
	
	/**
	 * Initiates and prepares the csv import process
	 *
	 * @param array $file
	 * @param string $table
	 *
	 * @returns array $response
	 */
	public function initializeCsvImport($file, $table) {
		
		$data = array();
		$errors = $this->validateUpload($file, $table);
		
		if (empty($errors)) {
			// Save the table we're importing to the session, as it is required in the final import stage
			$_SESSION['importer']['table'] = $table;
			
			$handle = fopen($_SESSION['importer']['file'], 'r');
			// Get csv headers
			$data[] = fgetcsv($handle);
			// Get table headers
			$data[] = $this->getTableFields();
			
			$this->saveTemporaryCsv($file);
		}
		
		$result = array("Data" => $data, "Errors" => $errors);
		
		return $result;
	}
	
	public function validateUpload($file, $table) {
		$errors = array();
		
		// Check that the upload is a valid file
		if (!is_uploaded_file($_FILES['csv']['tmp_name'])) {
			$errors[] = "No file was selected to upload. Please try again.";
		} else {
			// Validate file type
			$fileType = $this->validateCsvFileType($file);
			if ($fileType === false) {
				$errors[] = "Only CSV files can be used to import data. Please try again.";
			}
			
			// Check file size against the max size allowed
			if (filesize($file['tmp_name']) > $this->maxSize) {
				$errors[] = "The file to import must be no larger than 10 Mb. Split the file into smaller files and try again.";
			}
		}
		
		// Check that the import type selected wasn't tampered with or left empty
		if (!in_array($table, $this->supportedTables)) {
			$errors[] = "Please select a valid import type from the drop down menu.";
		}
		
		return $errors;
	}
	
	private function validateCsvFileType($file) {
		// Get file extension
		$temp = explode('.', $file['name']);
		$extension = end($temp);
		
		// Get mime type
		$fi = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($fi, $file['tmp_name']);
		finfo_close($fi);
		
		// Validate extension and mime type
		$validated = true;
		if (!in_array($extension, $this->supportedFileTypes)) {
			$validated = false;
		} else if (!in_array($mime, $this->supportedMimes)) {
			$validated = false;
		}
		
		return $validated;
	}
	
	private function saveTemporaryCsv($file) {
		// Appending something unique about the user who is uploading the file before $temp would be safest here
		$temp = mt_rand().'.csv';
		$path = ABS_PATH.'/files/'.$temp;
		if (!move_uploaded_file($file['tmp_name'], $path)) {
			throw new Exception("Oops! There was a problem uploading the file. Please try again.");	
		}
		
		// Store the file path in the session, as it will be needed later
		$_SESSION['importer']['file'] = $path;
	}
	
	public function getTableFields() {
		
		// NOTICE: This method should be put in a SEPARATE database class.
		// For demonstration and simplicity's sake, it will go here.
		$con = DatabaseConnect::getConnection();
		
		$sql = "SHOW COLUMNS FROM " . $_SESSION['importer']['table'];
		$stmt = $con->query($sql, PDO::FETCH_ASSOC);
		
		$fields = array();
		while ($row = $stmt->fetch()) {
			$fields[] = $row['Field'];
		}
		
		return $fields;
	}
	
}

?>