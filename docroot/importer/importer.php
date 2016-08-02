<?php

require_once('../../init.php');

// Execute request, if any, and initialize new page variables
$errors = array();
$include = 'uploadFile.phtml';

if (!empty($_POST['page'])) {
	$importer = new CsvImporter();
	
	if ($_POST['page'] == 'match') {
		$include = "uploadFile.phtml";
		
		// The matching section has been completed and we can proceed to importing
		$include = 'importComplete.phtml';
	} else {
		try {
			$result = $importer->initializeCsvImport($_FILES['csv'], $_POST['importType']);
			$errors = array_merge($result['Errors'], $errors);
		} catch (Exception $e) {
			$errors[] = $e->getMessage();
		}
		
		if (empty($errors)) {
			$include = 'matchFields.phtml';
		}
	}
}

require_once($include);