<?php

/**
 * @file
 * Contains \Drupal\project_importer\FileHandler\FileHandlerFactory.
 */

namespace Drupal\project_importer\FileHandler;

use Drupal\file\Entity\File;

use Drupal\project_importer\FileHandler\JSONFileHandler;
use Drupal\project_importer\FileHandler\OWLFileHandler;

class FileHandlerFactory {
    
    public static function createFileHandler($fid) {
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