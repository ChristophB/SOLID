<?php

/**
 * @file
 * Contains \Drupal\SOLID\Timer\Timer.
 */

namespace Drupal\SOLID\Timer;

/**
 * @author Christoph Beger
 */
class Timer {
    
    private $startTime;
    private $lastStop;
    
    public function __construct() {
        $this->startTime = time();
        $this->lastStop  = $this->startTime;
    }
    
    public function echoDiff($msg = null) {
        $time = time();
        echo ($msg ? $msg. ': ' : ''). ($time - $this->lastStop). PHP_EOL;
        $this->lastStop = $time;
    }
}

?>