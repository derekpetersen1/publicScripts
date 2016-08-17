<?php

/**
 * Controller class for csv file imports
 */
class CsvImporter {
	
	// Default max csv file size is 10 Mb.
	protected $maxSize = 10485760;
	
	// Supported table names must match what is in the database
	// Valid Name => Display Name
	public $supportedTables = array('User' => 'Users');
	
	// The number of inserts contained in a batch
	private $batchSize = 500;
	
	// Database connection.
	private $con;
	
	// Temp folder to save csv files
	private $path;
	
	// Counts record insertions/failures
	public $insertCounter = 0;
	protected $failedCounter = 0;
	
	// Stored csv tmp name
	public $tmp;
	
	// Stores any errors that occur in the import process
	public $errors = array();
	
	// Supported MySQL field types that are currently validated todo: Expand this list
	private $ints = array('int', 'tinyint', 'bigint');
	private $floats = array('float', 'double');
	private $dates = array('date', 'datetime', 'timestamp');
	
	// Define errors
	private $csvError = 'There was a problem reading the uploaded file. Please try again.';
	private $generalUploadError = 'Oops! There was a problem uploading the file. Please try again.';
	private $generalImportError = 'Oops! There was a problem importing the file. Please try again.';
	protected $partialErrorHeader = 'Er... Success! Sort of.';
	protected $completeErrorHeader = 'Houston... we have a problem.';
	
	/**
	 * Defines some of the page properties
	 */
	function __construct() {
		$this->con = DatabaseConnect::getConnection();
		$this->path = ABS_PATH.'/files/tmp/';
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
	
	/**
	 * Extracts the csv columns from a passed file path
	 *
	 * @param array $file
	 * @throws Exception
	 * 
	 * @returns array $csvColumns
	 */
	private function extractCsvColumns($file) {
		
		if (!$handle = fopen($file, 'r')) {
			throw new Exception($this->csvError);
		}
		$csvColumns = fgetcsv($handle);
		
		return $csvColumns;
	}
	
	// NOTICE: Database calls should be put in a SEPARATE database class.
	// For demonstration and simplicity's sake, it will go here.
	/**
	 * NOTICE: Database calls should be put in a SEPARATE database class.
	 * For demonstration and simplicity's sake, it will go here.
	 *
	 * Gets the table columns related to the import and returns them properly formatted
	 * 
	 * @param string $table
	 * @param bool $allInfo
	 * @param bool $format
	 * @throws Exception
	 *
	 * @returns array $fields
	 */
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
	
	/**
	 * Saves a csv file
	 *
	 * @param array $file
	 * @throws Exception
	 */
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
	
	/**
	 * Imports a csv file, tracks errors, counts successful and unsuccessful inserts
	 *
	 * @param array $matches
	 * @throws Exception
	 */
	public function importCsv($matches) {
		
		// Verify that the table fields haven't been tampered with
		$validTableFields = $this->extractTableColumns($_SESSION['importer']['table'], true, false);
		
		// Save auto matching to template history
		if ($_SESSION['importer']['autoMatching'] === true) {
			$this->saveAutoMatches($matches);
		}
		
		$passedCsvFields = array_keys($matches);
		$passedTableFields = array_values($matches);
		
		if (!$handle = fopen($_SESSION['importer']['file'], 'r')) {
			throw new Exception($this->csvError);
		}
		$allCsvHeaders = fgetcsv($handle);
		
		$inserts = array();
		$orderTableFields = array();
		$flaggedRows = array();
		$failedCounter = 0;
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
				
				$violation = $this->checkFieldViolations($matchedTableColumn, $value, $type, $null);
				if ($violation === true) {
					$fieldViolations++;
				}
				if (in_array($type, $this->dates)) {
					// Make a best attempt to format this to a time stamp
					$value = date('Y-m-d H:i:s', strtotime($value));
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
				$flaggedRows[] = $orderCsvFields;
				$failedCounter = $failedCounter + 1;
				
				// We don't want this array to get too big
				if (count($flaggedRows) === $this->batchSize) {
					// Determine what to write
					$this->tmp = basename($_SESSION['importer']['file']);
					$fileExists = is_file($this->path."error_$this->tmp") ? true : false;
					
					// Append to end of csv error file
					$errorHandle = fopen($this->path."error_$this->tmp",'a');
					if ($fileExists === false) {
						fputcsv($errorHandle, $allCsvHeaders);
					}
					foreach ($flaggedRows as $row) {
						fputcsv($errorHandle, $row);
					}
					
					fclose($errorHandle);
				}
			}
			
			// This only runs for larger imports to speed up insertion time
			if (count($inserts) === $this->batchSize) {
				try {
					$this->insertData($inserts, $orderTableFields, $validTableFields);
					$this->insertCounter = $this->insertCounter + $this->batchSize;
					$this->failedCounter = $this->failedCounter + $failedCounter;
				} catch (Exception $e) {
					$this->con->rollback();
					$this->errors[] = $this->generalImportError;
					$this->failedCounter = $this->failedCounter + (count($inserts) - $failedCounter);
					$flaggedRows[] = array_merge($flaggedRows, $inserts);
				}
				$inserts = array();
				$failedCounter = 0;
			}
		}
		fclose($handle);
		
		// For small inserts, or to take care of the last batch that had less than the batch size
		if (!empty($inserts)) {
			try {
				$this->insertData($inserts, $orderTableFields, $validTableFields);
				$this->insertCounter = $this->insertCounter + count($inserts);
				$this->failedCounter = $this->failedCounter + $failedCounter;
			} catch (Exception $e) {
				$this->con->rollback();
				$this->errors[] = $this->generalImportError;
				$this->failedCounter = $this->failedCounter + (count($inserts) - $failedCounter);
				$flaggedRows = array_merge($flaggedRows, $inserts);
			}
		}
		
		// Clean up leftover flagged rows
		if (!empty($flaggedRows)) {
			// Determine what to write
			$this->tmp = basename($_SESSION['importer']['file']);
			$fileExists = is_file($this->path."error_$this->tmp") ? true : false;
			
			// Append to end of csv error file
			$errorHandle = fopen($this->path."error_$this->tmp",'a');
			if ($fileExists === false) {
				fputcsv($errorHandle, $allCsvHeaders);
			}
			foreach ($flaggedRows as $row) {
				fputcsv($errorHandle, $row);
			}
			
			fclose($errorHandle);
		}
		
		$this->killImport();
	}
	
	/**
	 * Inserts the data into the database
	 *
	 * @param array $inserts
	 * @param array $tableFields
	 * @param array $validTableFields
	 * 
	 * @throws Exception
	 */
	private function insertData($inserts, $tableFields, $validTableFields) {
		$validTableFieldNames = array_keys($validTableFields);
		
		foreach ($tableFields as $passedField) {
			if (!in_array($passedField, $validTableFieldNames)) {
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
	
	/**
	 * Checks for MySQL field type errors to reduce the chances of insert errors
	 * This function ONLY CHECKS MySQL TYPES LISTED IN THE CORRELATING PROPERTY ARRAYS
	 *
	 * @param string $tableColumn
	 * @param string $csvValue
	 * @param string $type
	 * @param string $null
	 *
	 * @returns bool $violation
	 */
	private function checkFieldViolations($tableColumn, $csvValue, $type, $null) {
		$violation = false;
		// MySQL likes to add stuff to the end of type - aka "(40)". We only want the letters.
		$type = preg_replace('/[^a-z]/i','',$type);
		
		if (in_array($type, $this->ints)) {
			if (!ctype_digit($csvValue)) {
				$violation = true;
				$errorMessage = "$tableColumn allows numeric characters only.";
				if (!in_array($errorMessage, $this->errors)) {
					$this->errors[] = $errorMessage;
				}
			}
		} else if (in_array($type, $this->floats)) {
			$value = floatval($csvValue);
			if (!is_float($value) || $value == 0) {
				$violation = true;
				$errorMessage = "$tableColumn allows numeric characters with decimal values only. (ex. 3.14)";
				if (!in_array($errorMessage, $this->errors)) {
					$this->errors[] = $errorMessage;
				}
			}
		}
		
		if ($null == "NO") {
			if (empty($csvValue)) {
				$violation = true;
				$errorMessage = "$tableColumn doesn't allow empty values.";
				if (!in_array($errorMessage, $this->errors)) {
					$this->errors[] = $errorMessage;
				}
			}
		}
		
		return $violation;
	}
	
	/**
	 * Deletes any saved upload files and unsets the session
	 */
	protected function killImport() {
		unlink($_SESSION['importer']['file']);
		unset($_SESSION['importer']);
	}
	
	/**
	 * See if there's a matching template to the file being imported
	 *
	 * @param array $csvColumns
	 * @throws Exception
	 * 
	 * @returns array $matchingColumns
	 */
	protected function determineAutoMatches($csvColumns) {
		$sql = 'SELECT * FROM AutoMatchTemplate';
		$stmt = $this->con->query($sql, PDO::FETCH_ASSOC);
		$result = $stmt->fetchAll();
		
		// Format initial result
		$templates = array();
		foreach ($result as $row) {
			$templates[$row['AutoMatchID']]['csvFields'][] = $row['CsvField'];
			$templates[$row['AutoMatchID']]['tableFields'][] = $row['TableField'];
		}
		
		// See if a template exists. Has to match exactly, both order and field names
		$matchingColumns = array();
		foreach ($templates as $template) {
			if ($template['csvFields'] == $csvColumns) {
				$matchingColumns = $template['tableFields'];
				break;
			}
		}
		
		return $matchingColumns;
	}
	
	/**
	 * Inserts an auto match template
	 *
	 * @param array $matches
	 * @throws Exception
	 */
	private function saveAutoMatches($matches) {
		// Check for duplicate
		$csvFields = array_keys($matches);
		$duplicateCheck = $this->determineAutoMatches($csvFields);
		
		if (empty($duplicateCheck)) {
			$sql = "INSERT INTO AutoMatch (DateCreated) VALUES (NOW())";
			$this->con->exec($sql);
			
			$autoMatchID = $this->con->lastInsertID();
			foreach ($matches as $csvField => $tableField) {
				$sql = "INSERT INTO AutoMatchTemplate (AutoMatchID, CsvField, TableField) VALUES ($autoMatchID, :CsvField, :TableField)";
				$stmt = $this->con->prepare($sql);
				$prepare = array(
					'CsvField' => $csvField,
					'TableField' => $tableField
				);
				$stmt->execute($prepare);
			}	
		}
	}
}

?>