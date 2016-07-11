<?php

/**
 * @file
 * Contains \Drupal\project_importer\FileHandler\JSONFileHandler.
 */

namespace Drupal\project_importer\FileHandler;

class JSONFileHandler extends AbstractFileHandler {
	
	public function getData() {
		return $this->data;
	}
	
	public function getVocabularies() {
	    return $this->data['vocabularies'];
	}
	
	public function getNodes() {
	    return $this->data['nodes'];
	}
	
	protected function setData() {
		$this->data = json_decode($this->fileContent, TRUE);
		
		if (json_last_error() != 0) throw new Exception('Error: Could not decode the json file.');
	}
}
 
?>