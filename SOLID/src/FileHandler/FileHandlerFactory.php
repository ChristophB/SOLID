<?php

/**
 * @file
 * Contains \Drupal\SOLID\FileHandler\FileHandlerFactory.
 */

namespace Drupal\SOLID\FileHandler;

use Drupal\file\Entity\File;
use \Exception;

use Drupal\SOLID\FileHandler\JSONFileHandler;
use Drupal\SOLID\FileHandler\OWLFileHandler;
use Drupal\SOLID\FileHandler\OWLLargeFileHandler;

/**
 * Serves a FileHandler depending on the extension of the given file.
 * 
 * @author Christoph Beger
 */
class FileHandlerFactory {
    
    const THRESHOLD = 100000000; // 100 MBytes
    
    /**
     * Loads the file by its fid and checks file extension
     * to serve appropriate FileHandler.
     * 
     * @param @params array containing file path, VocabularyImporter and NodeImporter
     * @return FileHandler
     */
    public static function createFileHandler(array $params) {
    	if (empty($params)) throw new Exception('Error: no parameters provided.');
    	if (is_null($params['path'])) throw new Exception('Error: named parameter "path" missing.');
    	if (is_null($params['vocabularyImporter'])) throw new Exception('Error: named parameter "vocabularyImporter" missing.');
    	if (is_null($params['nodeImporter'])) throw new Exception('Error: named parameter "nodeImporter" missing.');
		
		switch (pathinfo($params['path'], PATHINFO_EXTENSION)) {
		    case 'json':
		        return new JSONFileHandler([
		        	'path'               => $params['path'],
		        	'vocabularyImporter' => $params['vocabularyImporter'],
		        	'nodeImporter'       => $params['nodeImporter']
		        ]);
		    case 'owl':
		    	// if (filesize($params['path']) > self::THRESHOLD) {
		    	// 	return new OWLLargeFileHandler($params);
		    	// } else {
		        	return new OWLFileHandler($params);
		    	// }
		    default:
		        throw new Exception('Error: input file format is not supported or file does not exist.');
		}
    }
}
