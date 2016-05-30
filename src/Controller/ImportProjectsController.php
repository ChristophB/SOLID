<?php

namespace Drupal\import_projects\Controller;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

class ImportProjectsController {
    public function import() {
        
        $this->import_taxonomy(
            'mit Service generiert', 
            [ 'test 1', 'test 2', 'test 3', 'test 4' ]
        );

        return [ '#title'  => 'Success!' ];
    }
    
    private function import_taxonomy($name, $categories) {
        if (!$name or !$categories) return;
        
        $vocabulary = Vocabulary::create([
            'name' => 'mit Service generiert',
            'weight' => 0,
            'vid' => '0815'
        ]);
        $vocabulary->save();
        
        $categories_vocabulary = '0815';
        
        foreach ($categories as $category) {
            Term::create([
                'parent' => [ ],
                'name'   => $category,
                'vid'    => $categories_vocabulary
            ])->save();
        }
    }
}