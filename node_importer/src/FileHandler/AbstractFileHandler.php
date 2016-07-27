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
	protected $vocabularyImporter;
	protected $nodeImporter;
	
	public function __construct($fid, $vocabularyImporter, $nodeImporter) {
		if (is_null($fid)) throw new Exception('Error: parameter $fid missing.');
		if (is_null($vocabularyImporter)) throw new Exception('Error: parameter $vocabularyImporter missing.');
		if (is_null($nodeImporter)) throw new Exception('Error: parameter $nodeImporter missing.');
		
		$this->data = [
			'vocabularies' => [],
			'nodes'        => [], 
		];
		
	    $file = File::load($fid);
	    
	    $this->filePath    = drupal_realpath($file->getFileUri());
	    $this->fileContent = file_get_contents($this->filePath);
	    
	    $this->vocabularyImporter = $vocabularyImporter;
	    $this->nodeImporter       = $nodeImporter;
	}
	
	abstract public function setVocabularyData();
	
	abstract public function setNodeData();
	
	abstract public function setData();
}
 
?>