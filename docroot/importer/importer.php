<?php

require_once('../../init.php');

/**
 * Controller class for csv file imports
 */
class ImporterPage extends CsvImporter {
	
	// These are always required to make this simple custom framework work. $template is the view.
	public $include = 'uploadFile.phtml';
	public $template = 'importer/importTemplate.phtml';
	
	private $startOver = '<input class="startOver" type="submit" name="startOver" value="< Reset" />';
	private $completed = false;
	
	// These are used in the views
	public $data;
	public $continueButton = 'Next';
	public $backButton = '';
	public $importStatus = 'Import Complete!';
	public $batchErrorMessage;
	public $failedMessage;
	public $successPlural;
	
	// Define errors
	private $noFile = 'No file was selected to upload';
	private $nonCsvFile = 'Only CSV files can be used to import data.';
	private $sizeLimitReached = 'The file to import must be no larger than 10 Mb. Split the file into smaller files and try again.';
	private $badImportType = 'Please select a valid import type from the drop down menu.';
	
	/**
	 * This method runs every time the page loads
	 */
	public function init() {
		if (!empty($_POST['page'])) {
			$this->determineState($_POST, $_FILES);
		}
		
		// This saves the state if the page is refreshed on the matching page
		if (isset($_SESSION['importer']) && $this->completed === false && empty($_POST['page'])) {
			$this->prepareImport($_SESSION['importer']['file'], $_SESSION['importer']['table'], $_SESSION['importer']['autoMatching']);
		}
	}
	
	/**
	 * Determines the current state of the importer and moves it towards the next state
	 *
	 * @param array $post
	 * @param array $files
	 */
	private function determineState($post, $files) {
		if (!empty($post['startOver'])) {
			$this->killImport();
		} else {
			if ($post['page'] == 'match') {
				$this->completeImport($post);
			} else {
				$autoMatching = !empty($post['autoMatching']) ? true : false;
				$this->prepareImport($files['csv'], $post['importType'], $autoMatching);
			}
		}
	}
	
	/**
	 * Prepares the csv importer for the match state
	 *
	 * @param array $file
	 * @param string $table
	 * @param bool $autoMatching
	 */
	private function prepareImport($file, $table, $autoMatching) {
		if (!empty($file['tmp_name'])) {
			$this->validateUpload($file, $table);
			$file = $file['tmp_name'];
		}
		
		if (empty($this->errors)) {
			try {
				$this->data = $this->initializeCsvImport($file, $table);
				
				$this->data['csvColumns'] = $this->prepareMatchData($this->data['csvColumns'], $autoMatching);
				$this->include = 'matchFields.phtml';
				$this->backButton = $this->startOver;
				$_SESSION['importer']['autoMatching'] = $autoMatching;
			} catch (Exception $e) {
				$this->errors[] = $e->getMessage();
			}
		}
	}
	
	/**
	 * Formats all the csv column data to prepare it for the matching section
	 *
	 * @param array $csvColumns
	 * @param bool $autoMatching
	 *
	 * @returns array $newData
	 */
	private function prepareMatchData($csvColumns, $autoMatching) {
		
		// Determine if they've imported with this template before
		if ($autoMatching === true) {
			$tableColumns = $this->determineAutoMatches($csvColumns);
		}
		
		// Loop through data to determine csv column image
		$newData = array();
		$counter = 0;
		foreach ($csvColumns as $key => $csv) {
			if (!empty($tableColumns)) {
				$image = 'blueCheckmark.png';
				$title = 'Auto Matched';
				$match = $tableColumns[$key];
			} else {
				// Determine image
				$image = 'redX.png';
				$title = 'Not Matched';
				$match = '';
				foreach ($this->data['tableColumns'] as $table) {
					if (stripos($table, $csv) !== FALSE) {
						$image = 'blueCheckmark.png';
						$title = 'Auto Matched';
						break;
					}
				}
			}
			
			$newData[$counter]['displayName'] = $csv;
			$newData[$counter]['name'] = str_replace(' ', '', $csv);
			$newData[$counter]['image'] = $image;
			$newData[$counter]['title'] = $title;
			$newData[$counter]['match'] = $match;
			
			$counter++;
		}
		
		return $newData;
	}
	
	/**
	 * Runs everything to complete the import and sets the messages that are displayed
	 *
	 * @param array $post
	 */
	private function completeImport($post) {
		$this->completed = true;
		
		$matches = array();
		foreach ($post as $key => $value) {
			if ($key == 'page' || $value == 'NotMatched') {
				continue;
			}
			$matches[str_replace('_', ' ', $key)] = $value;
		}
		
		// Import csv
		$this->importCsv($matches);
		
		$this->successPlural = ($this->insertCounter === 1) ? '' : 's';
		if (!empty($this->errors)) {
			$this->importStatus = ($this->insertCounter > 0) ? $this->partialErrorHeader : $this->completeErrorHeader;
			$this->failedMessage = ($this->failedCounter > 0) ? $this->failedCounter . ' failed.' : '';
		}
		
		$this->include = 'importComplete.phtml';
		$this->continueButton = 'Done';
	}
	
	/**
	 * Verifies that the csv upload is a proper csv file.
	 *
	 * @param array $file
	 * @param string $table
	 */
	private function validateUpload($file, $table) {
		// Check that the upload is a valid file
		if (!is_uploaded_file($file['tmp_name'])) {
			$this->errors[] = $this->noFile;
		} else {
			// Validate file type
			
			$vft = new Utilities();
			$fileType = $vft->validateCsvFile($file);
			if ($fileType === false) {
				$this->errors[] = $this->nonCsvFile;
			}
			
			// Check file size against the max size allowed
			if (filesize($file['tmp_name']) > $this->maxSize) {
				$this->errors[] = $this->sizeLimitReached;
			}
		}
		
		// Check that the import type selected wasn't tampered with or left empty
		if (!in_array($table, array_keys($this->supportedTables))) {
			$this->errors[] = $this->badImportType;
		}
	}
}

// This is required at the bottom of each view controller
$page = new ImporterPage();
$page->init();
require_once(ABS_PATH.'/includes/page.phtml');