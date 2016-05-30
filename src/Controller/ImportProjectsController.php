<?php

namespace Drupal\import_projects\Controller;

use Exception;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

class ImportProjectsController {
    const VOCABULARY = 'Importiert mit Modul';
    const VID = '0815';
    
    public function import() {
        try {
            self::createTaxonomy(
                self::VID,
                self::VOCABULARY, 
                [ 'test 1', 'test 2', 'test 3', 'test 4' ]
            );
        
            self::createProject();

            return [ '#title'  => 'Success!' ];
        } catch (Exception $e) {
            return [ 
                '#title' => 'Error!', 
                '#markup' => $e->getMessage()
            ];
        }
    }
    
    private function createTaxonomy($vid, $name, $categories) {
        // TODO hierarchies are ignored
        if (!$vid) throw new Exception('Error: parameter $vid missing');
        if (!$name) throw new Exception('Error: parameter $name missing');
        if (!$categories) throw new Exception('Error: parameter $categories missing');
    
        $vocabulary = self::createVocabulary($vid, $name);
        
        // TODO: get vid of created vocabulary
        $categories_vocabulary = '0815';
        
        foreach ($categories as $category) {
            Term::create([
                'parent' => [ ],
                'name'   => $category,
                'vid'    => $categories_vocabulary
            ])->save();
        }
    }
    
    private function createVocabulary($vid, $name) {
        if (!$vid) throw new Exception('Error: parameter $vid missing');
        if (!$name) throw new Exception('Error: parameter $name missing');
        
        $vocabulary = Vocabulary::create([
            'name' => $name,
            'weight' => 0,
            'vid' => $vid
        ]);
        $vocabulary->save();
        
        return $vocabulary;
    }
    
    private function createProject() {}
}