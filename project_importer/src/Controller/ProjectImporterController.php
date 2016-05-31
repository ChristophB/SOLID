<?php

namespace Drupal\project_importer\Controller;

use Exception;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;

class ProjectImporterController {
    const VOCABULARY = 'Importiert mit Modul';
    const VID = '0815';
    const PRJ_TITLE = 'Beispiel-Projekt';
    const USER = 1;
    const PRJ_CONTENT = '<b>Test Inhalt</b>';
    
    public function import() {
        try {
            self::createTaxonomy(
                self::VID,
                self::VOCABULARY, 
                [ 'test 1', 'test 2', 'test 3', 'test 4' ]
            );
        
            self::createProject([
                'title' => self::PRJ_TITLE,
                'uid' => self::USER,
                'content' => self::PRJ_CONTENT
            ]);

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
    
    private function createProject($params) {
        if (!$params['title']) throw new Exception('Error: named parameter "title" missing');
        
        $node = Node::create([
            'type'     => 'article',
            'title'    => $params['title'],
            'langcode' => 'de',
            'status'   => 1,
            'uid'      => $params['uid'],
            'body'     => [
                'value'  => $params['content'],
                'format' => 'basic_html'
            ]
        ]);
        $node->save();
    }
}