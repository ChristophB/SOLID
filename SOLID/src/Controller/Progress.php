<?php

/**
 * @file
 * Contains \Drupal\SOLID\Controller\Progress.
 */

namespace Drupal\SOLID\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * This class provides a view containing messages of a currently running import.
 * 
 * @author Christoph Beger
 */
class Progress extends ControllerBase {
    private $logFile = 'modules/SOLID/SOLID.log';
    
    public function content() {
        $log = '';
        if (file_exists($this->logFile)) {
            foreach (array_reverse(file($this->logFile)) as $line)
                $log .= $line;
        } else {
            $log = 'No import process running!';
        }
        
        $formLink     = Link::createFromRoute('Form', 'SOLID');
        $progressLink = Link::createFromRoute('Progress', 'SOLID.progress');
        
        return [
            '#type' => 'markup',
            '#markup'
                =>'<nav class="tabs" role="navigation" aria-label="Tabs">'
                . '  <h2 class="visually-hidden">Primary tabs</h2>'
                . '  <ul class="tabs primary tabs--primary nav nav-tabs">'
                . "    <li>{$formLink->toString()}</li>"
                . "    <li class='is-active active'>{$progressLink->toString()}<span class='visually-hidden'>(active tab)</span></li>"
                . '  </ul>'
                . '</nav>'
                . "<pre>$log</pre>"
        ];
    }
  
}

?>