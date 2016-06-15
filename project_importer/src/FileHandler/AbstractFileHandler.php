<?php

/**
 * @file
 * Contains \Drupal\project_importer\FileHandler\AbstractFileHandler.
 */

namespace Drupal\project_importer\FileHandler;

use Drupal\file\Entity\File;

abstract class AbstractFileHandler {
	protected $filePath;
	protected $fileContent;
	protected $data;
	
	public function __construct($fid) {
		$this->data = [ 'vocabularies' => [], 'nodes' => [] ];
		
	    $file = File::load($fid);
	    
	    $this->filePath = drupal_realpath($file->getFileUri());
	    $this->fileContent = file_get_contents($this->filePath);
	    $this->setData();
	}
	
	abstract public function getData();
	
	abstract public function getVocabularies();
	
	abstract public function getNodes();
	
	abstract protected function setData();
}
 
?>