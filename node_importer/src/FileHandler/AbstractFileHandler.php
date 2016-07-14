<?php

/**
 * @file
 * Contains \Drupal\node_importer\FileHandler\AbstractFileHandler.
 */

namespace Drupal\node_importer\FileHandler;

use Drupal\file\Entity\File;

/**
 * Abstract class which describes FileHandlers. All FileHandlers extend this class.
 * A FileHandler parses a given file and translates the content to a php object.
 * 
 * @author Christoph Beger
 */
abstract class AbstractFileHandler {
	protected $filePath;
	protected $fileContent;
	protected $data;
	
	public function __construct($fid) {
		if (!$fid) throw new Exception('Error: parameter $fid missing.');
		
		$this->data = [
			'vocabularies' => [],
			'nodes'        => [], 
		];
		
	    $file = File::load($fid);
	    
	    $this->filePath = drupal_realpath($file->getFileUri());
	    $this->fileContent = file_get_contents($this->filePath);
	    $this->setData();
	}
	
	public function getData() {
		return $this->data;
	}
	
	public function getVocabularies() {
	    return $this->data['vocabularies'];
	}
	
	public function getNodes() {
	    return $this->data['nodes'];
	}
	
	abstract protected function setData();
}
 
?>