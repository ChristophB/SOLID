<?php

/**
 * @file
 * Contains \Drupal\node_importer\FileHandler\FileHandlerFactory.
 */

namespace Drupal\node_importer\FileHandler;

use Drupal\file\Entity\File;

use Drupal\node_importer\FileHandler\JSONFileHandler;
use Drupal\node_importer\FileHandler\OWLFileHandler;

/**
 * Serves a FileHandler depending on the extension of the given file
 * 
 * @author Christoph Beger
 */
class FileHandlerFactory {
    
    /**
     * Loads the file by its fid and checks file extension
     * to serve appropriate FileHandler.
     * 
     * @param $fid fid of the uploaded file
     * @return FileHandler
     */
    public static function createFileHandler($params) {
    	if (empty($params)) throw new Exception('Error: no parameters provided.');
    	if (is_null($params['fid'])) throw new Exception('Error: named parameter "fid" missing.');
    	if (is_null($params['vocabularyImporter'])) throw new Exception('Error: named parameter "vocabularyImporter" missing.');
    	if (is_null($params['nodeImporter'])) throw new Exception('Error: named parameter "nodeImporter" missing.');
    	
        $uri = File::load($params['fid'])->getFileUri();
		
		switch (pathinfo(drupal_realpath($uri), PATHINFO_EXTENSION)) {
		    case 'json':
		        return new JSONFileHandler([
		        	'fid'                => $params['fid'],
		        	'vocabularyImporter' => $params['vocabularyImporter'],
		        	'nodeImporter'       => $params['nodeImporter']
		        ]);
		    case 'owl':
		        return new OWLFileHandler($params);
		    default:
		        throw new Exception('Error: input file format is not supported.');
		}
    }
}