<?php

/**
 * @file
 * Contains \Drupal\project_importer\Importer\AbstractImporter.
 */

namespace Drupal\project_importer\Importer;

use Exception;

abstract class AbstractImporter {
    protected $entities = [];
    protected $overwrite = false;
     
    abstract public function import($data, $overwrite = false);
     
    public function rollback() {
         foreach ($this->entities as $type => $entities) {
			foreach ($entities as $entity) {
				$entity->delete();
			}
		}
     }
     
    protected function searchEntityIds($params) {
		if (!$params['entity_type']) throw new Exception('Error: named parameter "entity_type" missing');
		
		$query = \Drupal::entityQuery($params['entity_type']);
		
		foreach ($params as $key => $value) {
			if ($key == 'entity_type') continue;
			$query->condition($key, $value);
		}
		
		return $query->execute();
	}
	
	protected function searchTagIdByName($vid, $name) {
	    if (!$vid) throw new Exception('Error: parameter $vid missing');
	    if (!$name) throw new Exception('Error: parameter $name missing');
	    
	    $result = $this->searchEntityIds([
	        'entity_type' => 'taxonomy_term',
	        'vid'         => $vid,
	        'name'        => $name
	    ]);
	    
	    if (!$result) return null;
	    
	    return array_values($result)[0];
	}
	
	protected function searchTagIdsByNames($tags) {
		if (empty($tags)) return [];
		
		return array_map(
			function($tag) { return $this->searchTagIdByName($tag['vid'], $tag['name']); }, 
			$tags
		);
	}
	
}
 
?>