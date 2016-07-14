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
    public static function createFileHandler($fid) {
    	if (!$fid) throw new Exception('Error: parameter $fid missing.');
    	
        $uri = File::load($fid)->getFileUri();
		
		switch (pathinfo(drupal_realpath($uri), PATHINFO_EXTENSION)) {
		    case 'json':
		        return new JSONFileHandler($fid);
		    case 'owl':
		        return new OWLFileHandler($fid);
		    default:
		        throw new Exception('Error: input file format is not supported.');
		}
    }
}