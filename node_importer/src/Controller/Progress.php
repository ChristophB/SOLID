<?php

/**
 * @file
 * Contains \Drupal\node_importer\Controller\Progress.
 */

namespace Drupal\node_importer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * This class provides a view containing messages of a currently running import.
 * 
 * @author Christoph Beger
 */
class Progress extends ControllerBase {
    private $logFile = 'modules/node_importer/node_importer.log';
    
    public function content() {
        if (file_exists($this->logFile)) {
            $log = file_get_contents($this->logFile);
        } else {
            $log = 'No import process running!';
        }
        
        $formLink     = Link::createFromRoute('Form', 'node_importer');
        $progressLink = Link::createFromRoute('Progress', 'node_importer.progress');
        
        return [
            '#type' => 'markup',
            '#markup'
                =>'<ul>'
                . '  <li>'. $formLink->toString(). '</li>'
                . '  <li>'. $progressLink->toString(). '</li>'
                . '</ul>'
                . "<pre>$log</pre>"
        ];
    }
  
}

?>