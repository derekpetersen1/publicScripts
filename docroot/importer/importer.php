<?php

require_once('../../init.php');

/**
 * Controller class for csv file imports
 */
class ImporterPage extends CsvImporter {
	
	// These are required to make this simple custom framework work
	public $include;
	public $template = 'importer/importTemplate.phtml';
	
	// These are used in the view
	public $data;
	public $button;
	public $importStatus = 'Import Complete!';
	public $batchErrorMessage;
	
	// Define errors
	private $noFile = 'No file was selected to upload';
	private $nonCsvFile = 'Only CSV files can be used to import data.';
	private $sizeLimitReached = 'The file to import must be no larger than 10 Mb. Split the file into smaller files and try again.';
	private $badImportType = 'Please select a valid import type from the drop down menu.';
	
	public function init() {
		
		//unset($_SESSION['importer']);
		
		$this->include = 'uploadFile.phtml';
		$this->button = 'Next';
		
		if (!empty($_POST['page'])) {
			if ($_POST['page'] == 'match') {
				$completed = true;
				$this->completeImport($_POST);
			} else {
				$this->prepareImport($_FILES['csv'], $_POST['importType']);
			}
		}
		
		// This saves the state if the page is refreshed
		if (isset($_SESSION['importer']) && empty($completed)) {
			$this->data = $this->initializeCsvImport($_SESSION['importer']['file'], $_SESSION['importer']['table']);
			$this->include = 'matchFields.phtml';
			$this->button = 'Finish';
		}
		
		if (!empty($completed)) {
			$this->include = 'importComplete.phtml';
			$this->button = "Done";
		}
	}
	
	private function prepareImport($file, $table) {
		$this->validateUpload($file, $table);
		
		if (empty($this->errors)) {
			try {
				$this->data = $this->initializeCsvImport($file['tmp_name'], $table);
				$this->include = 'matchFields.phtml';
			} catch (Exception $e) {
				$this->errors[] = $e->getMessage();
			}
		}
	}
	
	private function completeImport($post) {
		
		$matches = array();
		foreach ($post as $key => $value) {
			if ($key == 'page' || $value == 'NotMatched') {
				continue;
			}
			$matches[str_replace('_', ' ', $key)] = $value;
		}
		
		$this->importCsv($matches);
		
		if (!empty($this->errors)) {
			if ($this->insertCounter > 0) {
				$this->importStatus = $this->partialErrorHeader;
				$this->batchErrorMessage = $this->partialErrorMessage;
			} else {
				$this->importStatus = $this->completeErrorHeader;
				$this->batchErrorMessage = $this->completeErrorMessage;
			}
		}
	}
	
	private function validateUpload($file, $table) {
		// Check that the upload is a valid file
		if (!is_uploaded_file($_FILES['csv']['tmp_name'])) {
			$this->errors[] = $this->noFile;
		} else {
			// Validate file type
			$fileType = $this->validateCsvFileType($file);
			if ($fileType === false) {
				$this->errors[] = $this->nonCsvFile;
			}
			
			// Check file size against the max size allowed
			if (filesize($file['tmp_name']) > $this->maxSize) {
				$this->errors[] = $this->sizeLimitReached;
			}
		}
		
		// Check that the import type selected wasn't tampered with or left empty
		if (!in_array($table, $this->supportedTables)) {
			$this->errors[] = $this->badImportType;
		}
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
	
	
	
}

$page = new ImporterPage();
$page->init();
require_once(ABS_PATH.'/includes/page.phtml');