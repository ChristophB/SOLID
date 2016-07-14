<?php

/**
 * @file
 * Contains \Drupal\node_importer\FileHandler\JSONFileHandler.
 */

namespace Drupal\node_importer\FileHandler;

/**
 * FileHandler which parses JSON files.
 * 
 * @author Christoph Beger
 */
class JSONFileHandler extends AbstractFileHandler {
	
	protected function setData() {
		$this->data = json_decode($this->fileContent, TRUE);
		
		if (json_last_error() != 0) throw new Exception('Error: Could not decode the json file.');
	}
}
 
?>