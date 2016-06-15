<?php

/**
 * @file
 * Contains \Drupal\project_importer\Importer\VocabularyImporter.
 */

namespace Drupal\project_importer\Importer;

use Exception;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

class VocabularyImporter extends AbstractImporter {
    
    function __construct() {
        $this->entities['vocabulary'] = [];
        $this->entities['tag'] = [];
    }
    
    public function getTags() {
        return $this->entities['tag'];
    }
    
    public function import($data, $overwrite = false) {
        if ($overwrite) $this->overwrite = true;
        if (empty($data)) return;
        
        foreach ($data as $vocabulary) {
            $this->createVocabulary($vocabulary['vid'], $vocabulary['name']);
            $this->createTags($vocabulary['vid'], $vocabulary['tags']);
		    $this->setTagParents($vocabulary['vid'], $vocabulary['tags']);
        }
    }
    
    public function countCreatedVocabularies() {
        return sizeof($this->entities['vocabulary']);
    }
    
    public function countCreatedTags() {
        return sizeof($this->entities['tag']);
    }
    
    private function createVocabulary($vid, $name) {
		if (!$vid) throw new Exception('Error: parameter $vid missing');
		if (!$name) throw new Exception('Error: parameter $name missing');
		
		$this->deleteVocabularyIfExists($vid);
		
		$vocabulary = Vocabulary::create([
			'name'   => $name,
			'weight' => 0,
			'vid'    => $vid
		]);
		$vocabulary->save();
		array_push($this->entities['vocabulary'], $vocabulary);
	}
	
	private function createTags($vid, $tags) {
	    if (!$vid) throw new Exception('Error: parameter $vid missing');
	    if (empty($tags)) return;
	    
	    foreach ($tags as $tag) {
			$term = Term::create([
				'name'   => $tag['name'],
				'vid'    => $vid,
			]);
			$term->save();
			
			array_push($this->entities['tag'], $term);
		}
	}
	
	private function deleteVocabularyIfExists($vid) {
		if ($id = $this->searchVocabularyByVid($vid)) {
			if ($this->overwrite) {
				Vocabulary::load($id)->delete();
			} else {
				throw new Exception(
					"Error: vocabulary with vid '$vid' already exists. "
					. 'Tick "overwrite" if you want to replace it and try again.'
				);
	    	}
	    }
	}
	
	private function searchVocabularyByVid($vid) {
		if (!$vid) throw new Exception('Error: parameter $vid missing');
		
		return array_values($this->searchEntityIds([
			'vid'         => $vid,
			'entity_type' => 'taxonomy_vocabulary',
		]))[0];
	}
	
	private function setTagParents($vid, $tags) {
		foreach ($tags as $tag) {
			if (empty($tag['parents'])) continue;
			
			$tagEntity = Term::load($this->searchTagIdByName($vid, $tag['name']));
			
			$tagEntity->parent->setValue($this->searchTagIdsByNames(
			    array_map(
			        function($parent) use($vid) { return [ 'vid' => $vid, 'name' => $parent ]; }, 
			        $tag['parents']
			    )
			));
			$tagEntity->save();
		}
	}
	
}
 
?>