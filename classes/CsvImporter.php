<?php

/**
 * Controller class for csv file imports
 */
class CsvImporter {
	
	// Default max csv file size is 10 Mb.
	protected $maxSize = 10485760;
	
	// Supported table names must match what is in the database
	protected $supportedTables = array('User');
	
	// Supported file types
	protected $supportedFileTypes = array('csv');
	
	// Supported file mime types
	protected $supportedMimes = array('application/vnd.ms-excel','text/plain','text/csv','text/tsv');
	
	// The number of inserts contained in a batch
	private $batchSize = 500;
	
	// Database connection.
	private $con;
	
	// Temp folder to save csv files
	private $path;
	
	// Counts successful record insertions
	public $insertCounter = 0;
	
	// Stores any errors that occur in the import process
	public $errors = array();
	
	// Supported MySQL field types that are currently validated todo: Expand this list
	private $ints = array('INT', 'TINYINT', 'BIGINT');
	private $floats = array('FLOAT', 'DOUBLE');
	
	// Define errors
	private $csvError = 'There was a problem reading the uploaded file. Please try again.';
	private $generalUploadError = 'Oops! There was a problem uploading the file. Please try again.';
	private $generalImportError = 'Oops! There was a problem importing the file. Please try again.';
	protected $partialErrorHeader = 'Er... Success! Sort of.';
	protected $partialErrorMessage = 'Some of the CSV rows had problems. One or more of your rows had the following problems:';
	protected $completeErrorHeader = 'Houston... we have a problem.';
	protected $completeErrorMessage = 'All of the CSV rows had problems. One or more of your rows had the following problems:';
	
	function __construct() {
		$this->con = DatabaseConnect::getConnection();
		$this->path = ABS_PATH.'/files/';
	}
	
	/**
	 * Initiates and prepares the csv import process
	 *
	 * @param array $file
	 * @param string $table
	 *
	 * @returns array $data
	 */
	public function initializeCsvImport($file, $table) {
		$data = array();
		
		// Save the table we're importing to the session, as it is required in the final import stage
		$_SESSION['importer']['table'] = $table;
		
		// Get csv headers
		$data['csvColumns'] = $this->extractCsvColumns($file);
		// Get table headers
		$data['tableColumns'] = $this->extractTableColumns($table, false, true);
		array_unshift($data['tableColumns'], 'Not Matched');
		
		if (!isset($_SESSION['importer']['file'])) {
			$this->saveTemporaryCsv($file);
		}
		
		return $data;
	}
	
	private function extractCsvColumns($file) {
		
		if (!$handle = fopen($file, 'r')) {
			throw new Exception($this->csvError);
		}
		$csvColumns = fgetcsv($handle);
		
		return $csvColumns;
	}
	
	// NOTICE: Database calls should be put in a SEPARATE database class.
	// For demonstration and simplicity's sake, it will go here.
	private function extractTableColumns($table, $allInfo = false, $format = false) {
		try {
			$sql = "SHOW COLUMNS FROM " . $table;
			$stmt = $this->con->query($sql, PDO::FETCH_ASSOC);
		} catch (Exception $e) {
			throw new Exception($this->generalUploadError);
		}
		
		$fields = array();
		// Set a counter so that we don't store the primary key
		$counter = -1;
		while ($row = $stmt->fetch()) {
			$counter++;
			if ($counter < 1) {
				continue;
			}
			
			if ($format === true) {
				// Format the table fields so that they're display friendly
				$chunks = preg_split('/(?=[A-Z])/', $row['Field']);
				$row['Field'] = trim(implode(" ", $chunks));
			}
			
			if ($allInfo === true) {
				$fields[$row['Field']] = $row;
			} else {
				$fields[] = $row['Field'];
			}
		}
		
		return $fields;
	}
	
	private function saveTemporaryCsv($file) {
		// Appending something unique about the user who is uploading the file before $temp would be safest here
		$temp = mt_rand().'.csv';
		$path = $this->path.$temp;
		if (!move_uploaded_file($file, $path)) {
			throw new Exception($this->generalUploadError);
		}
		
		// Store the file path in the session, as it is required in the final import stage
		$_SESSION['importer']['file'] = $path;
	}
	
	public function importCsv($matches) {
		
		// Verify that the table fields haven't been tampered with
		$validTableFields = $this->extractTableColumns($_SESSION['importer']['table'], true, false);
		
		$passedCsvFields = array_keys($matches);
		$passedTableFields = array_values($matches);
		
		if (!$handle = fopen($_SESSION['importer']['file'], 'r')) {
			throw new Exception($this->csvError);
		}
		$allCsvHeaders = fgetcsv($handle);
		
		$inserts = array();
		$orderTableFields = array();
		$flaggedRows = array();
		while (($data = fgetcsv($handle, 5000, ",")) !== FALSE) {
			$row = array_combine($allCsvHeaders, $data);
			
			$orderCsvFields = array();
			$fieldViolations = 0;
			foreach ($row as $key => $value) {
				if (!in_array($key, $passedCsvFields)) {
					continue;
				}
				
				// Check what table column this key was matched up against, and check if it's the correct data type.
				$matchedTableColumn = $matches[$key];
				$type = $validTableFields[$matchedTableColumn]['Type'];
				$null = $validTableFields[$matchedTableColumn]['Null'];
				
				$violation = $this->checkFieldViolations($matchedTableColumn, $key, $value, $type, $null);
				if ($violation === true) {
					$fieldViolations++;
				}
				
				// Order each csv and table array so that the associated values are in the same left-to-right order
				$orderCsvFields[] = $value;
				if (count($orderTableFields) < count($passedTableFields)) {
					$orderTableFields[] = $matchedTableColumn;
				}
			}
			if ($fieldViolations < 1) {
				$inserts[] = $orderCsvFields;
			} else {
				$flaggedRows[] = array_values($row);
				
				// We don't want this array to store too much
				if (count($flaggedRows) === $this->batchSize) {
					// Write to temp folder
					
					$flaggedRows = array();
				}
			}
			
			// This only runs for larger imports to speed up insertion time
			if (count($inserts) === $this->batchSize) {
				try {
					$this->insertData($inserts, $orderTableFields, $validTableFields);
					$this->insertCounter = $this->insertCounter + $this->batchSize;
				} catch (Exception $e) {
					$this->con->rollback();
					$this->errors[] = $this->generalImportError;
				}
				$this->insertCounter = $this->insertCounter + $this->batchSize;
				$inserts = array();
			}
		}
		fclose($handle);
		
		// For small inserts, or to take care of the last batch that had less than the batch size
		if (!empty($inserts)) {
			try {
				$this->insertData($inserts, $orderTableFields, $validTableFields);
				$this->insertCounter = $this->insertCounter + count($inserts);
			} catch (Exception $e) {
				$this->con->rollback();
				$this->errors[] = $this->generalImportError;
			}
		}
		
		if (!empty($flaggedRows)) {
			// Write to temp folder
		}
		
		unlink($_SESSION['importer']['file']);
		unset($_SESSION['importer']);
	}
	
	private function insertData($inserts, $tableFields, $validTableFields) {
		foreach ($tableFields as $passedField) {
			if (!in_array($passedField, $validTableFields)) {
				// Fields have been tampered with on the front end. Throw a general error.
				$this->errors[] = $this->generalImportError;
				throw new Exception();
			}
		}
		
		// Format data for prepared statements
		$questionMarks = array();
		$prepareInserts = array();
		foreach ($inserts as $insert) {
			$questionMarks[] = '(' . implode(',', array_fill(0, count($insert), '?')) . ')';
			$prepareInserts = array_merge($insert, $prepareInserts);
		}
		
		$this->con->beginTransaction();
		
		$table = $_SESSION['importer']['table'];
		$sql = "INSERT INTO $table (" . implode(', ', $tableFields) . ")
				VALUES " . implode(',', $questionMarks);
		$stmt = $this->con->prepare($sql);
		$stmt->execute($prepareInserts);
		
		$this->con->commit();
	}
	
	// This function ONLY CHECKS MySQL TYPES LISTED IN THE CORRELATING PROPERTY ARRAYS
	private function checkFieldViolations($tableColumn, $csvColumn, $csvValue, $type, $null) {
		$violation = false;
		
		if (in_array($type, $this->ints)) {
			if (!ctype_digit($csvValue)) {
				$violation = true;
				$errorMessage = "You matched your CSV header $csvColumn with $tableColumn. $tableColumn strictly allows numeric characters only.";
				if (!in_array($errorMessage, $this->errors)) {
					$this->errors[] = $errorMessage;
				}
			}
		} else if (in_array($type, $this->floats)) {
			$value = floatval($csvValue);
			if (!is_float($value) || $value === 0) {
				$violation = true;
				$errorMessage = "You matched your CSV header $csvColumn with $tableColumn. $tableColumn strictly allows numeric characters with decimal values only. (ex. 3.14)";
				if (!in_array($errorMessage, $this->errors)) {
					$this->errors[] = $errorMessage;
				}
			}
		}
		
		if ($null == "NO") {
			if (empty($csvValue)) {
				$violation = true;
				$errorMessage = "You matched your CSV header $csvColumn with $tableColumn. $tableColumn doesn't allow empty values.";
				if (!in_array($errorMessage, $this->errors)) {
					$this->errors[] = $errorMessage;
				}
			}
		}
		
		return $violation;
	}
}

?>