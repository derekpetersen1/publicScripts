<?php

require_once('../init.php');

// For security reasons, it's recommend that:
// (1) The user has to be logged in to access this page
// (2) The user is denied the ability to download anything they don't have access to

$fileType = $_GET['fileType'];
$file = 'error_'.$_GET['file'];
$feature = $_GET['feature'];
$validated = false;

switch ($feature) {
	case 'importer':
		$newFileName = "csvImportErrors";
		$path = ABS_PATH.'/files/tmp/';
		break;
}

switch ($fileType) {
	case 'csv':
		$extension = '.csv';
		$header = 'text/csv';
		
		// To take extra precaution, verify the file type
		$vcf = new Utilities();
		$validated = $vcf->validateCsvFileType($path.$file, true);
		break;
}

$newFileName = $newFileName.$extension;
$fileSize = filesize($path.$file);

if (is_file($path.$file) && $validated === true) {
	$openFile = fopen($path.$file, 'rb');
	if ($openFile) {
		header('Pragma: public');
		header('Expires: -1');
		header('Cache-Control: public, must-revalidate, post-check=0, pre-check=0');
		header("Content-Disposition: attachment; filename=\"$newFileName\"");
		header("Content-Type: $header");
		header("Content-Length: $fileSize");
		set_time_limit(0);
		
		while(!feof($openFile)) {
			echo fread($openFile, $fileSize);
			ob_flush();
			flush();
		}
		
		fclose($openFile);
	} else {
		echo 'Oops! Something unexpected happened and this file is no longer available.';
	}
}