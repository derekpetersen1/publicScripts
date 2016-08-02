<?php

/**
 * Class to autoload everything in the classes folder
 */
class Autoloader {
	
	/**
	 * Recursively iterates through the classes directory to match a given class name
	 * The include path will be null if a matching class is not found
	 * 
	 * @param string $class
	 * @includes string $file
	 */
	public static function loader($class) {
		$fileName = $class.'.php';
		$path = realpath(ABS_PATH.'/classes/');
		$ignore = array('.', '..', '.gitkeep');
		$file = null;
		
		$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
		foreach($objects as $name => $object){
			$item = basename($name);
			// Continue through items in ignore array
			if (in_array($item, $ignore)) {
				continue;
			}
			
			// If it found the class we want, break
			if ($item == $fileName) {
				$file = $name;
				break;
			}
		}
		
		include $file;
	}
}

?>