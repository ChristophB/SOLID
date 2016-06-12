<?php

/**
 * @file
 * Contains \Drupal\project_importer\Importer\NodeImporter.
 */

namespace Drupal\project_importer\Importer;

use Exception;

use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;

class NodeImporter extends AbstractImporter {
    private $nodeReferences = []; // [ 'NodeID' => [ 'field_name' => [ 'refEntityType' => [ 'EntityTitle', ... ] ] ] ]

    function __construct() {
        $this->entities['node'] = [];
        $this->entities['file'] = [];
    }
    
    public function import($data, $overwrite = false) {
        if ($overwrite) $this->overwrite = true;
        if (empty($data)) return;
        
        foreach ($data as $node) {
		    $this->createNode($node);
	    }
	    $this->insertNodeReferences();
    }
    
    public function countCreatedNodes() {
        return sizeof($this->entities['node']);
    }
    
    public function countCreatedFiles() {
        return sizeof($this->entities['file']);
    }
    
    private function createNode($params) {
		if (!$params['title']) throw new Exception('Error: named parameter "title" missing');

		$this->deleteNodeIfExists($params['title']);

		$node = Node::create([
			'type'     => 'article',
			'title'    => $params['title'],
			'langcode' => 'de',
			'status'   => 1,
			'uid'      => \Drupal::currentUser()->id(),
			'body'     => [
				'value'   => $params['content'],
				'summary' => $params['summary'],
				'format'  => 'basic_html',
			],
			'field_tags'  => $this->searchTagIdsByNames($params['tags']),
			'field_image' => $this->constructFieldImage($params['img']),
		]);
		
		$node->save();
		$this->insertCustomFields($node, $params['custom_fields']);
		$node->save();
			
		$this->addAlias([
			'id'    => $node->id(),
			'alias' => $params['alias']
		]);
		array_push($this->entities['node'], $node);
		
		return $node;
	}
	
	private function createFile($uri) {
		if (!$uri) throw new Exception('Error: parameter $uri missing');
		
		$file = File::create([
			'uid'    => \Drupal::currentUser()->id(),
			'uri'    => $uri,
			'status' => 1,
		]);
		$file->save();
		array_push($this->entities['file'], $file);
			
		return $file;
	}
	
	private function deleteNodeIfExists($title) {
		if (!empty($ids = $this->searchNodesByTitle($title))) {
			if ($this->overwrite) {
				foreach ($ids as $id) {
					Node::load($id)->delete();
				}
			} else {
				throw new Exception(
					"Node with title '$title' already exists. "
					. 'Tick "overwrite" if you want to replace it and try again.'
				);
			}
		}
	}
	
	private function searchNodesByTitle($title) {
		if (!$title) throw new Exception('Error: parameter $title missing');
		
		return $this->searchEntityIds([
			'title'       => $title,
			'entity_type' => 'node',
		]);
	}
	
	private function constructFieldImage($img) {
		if (!$img) return [];
		
		$file = $this->createFile($img['uri']);
		
		return [
			'target_id' => $file->id(),
			'alt'       => $img['alt'],
			'title'     => $img['title'],
		];
	}
	
	private function insertCustomFields($node, $customFields) {
		if (empty($customFields)) return;
		
		foreach ($customFields as $fieldContent) {
			if ($refEntityType = $fieldContent['references']) {
				$this->nodeReferences[$node->id()][$fieldContent['field_name']][$refEntityType]
					= $fieldContent['value'];
			} else {
				$node->get($fieldContent['field_name'])->setValue($fieldContent['value']);
			}
		}
	}
	
	private function addAlias($params) {
		if (!$params['id']) throw new Exception('Error: named parameter "id" missing');
		if (!$params['alias']) return;
		
		\Drupal::service('path.alias_storage')->save(
			'/node/'. $params['id'], 
			'/'. $params['alias'], 
			'de'
		);
	}
	
	private function insertNodeReferences() {
		foreach ($this->nodeReferences as $pid => $field) {
			foreach ($field as $fieldName => $reference) { // assumption: only one entitytype per field
				foreach ($reference as $entityType => $entityNames) {
					$entityIds = [];
					
					switch ($entityType) {
						case 'tag': 
							$entityIds = $this->searchTagIdsByNames($entityNames);
							break;
						case 'node':
							$entityIds = $this->mapNodeTitlesToNids($entityNames);
							break;
						case 'file':
							$entityIds = $this->mapFileUrisToFids($entityNames);
							break;
						default:
							throw new Exception("Error: unsupported entity type '$entityType' in reference found");
					}
					$node = Node::load($pid);
					$node->get($fieldName)->setValue($entityIds);
					$node->save();
				}
			}
		}
	}
	
	private function mapNodeTitlesToNids($titles) {
		if (empty($titles)) return [];
		
		return array_map(
			function($title) { return $this->mapNodeTitleToNid($title); }, 
			$titles
		);
	}
	
	private function mapNodeTitleToNid($title) {
		if (!$title) return null;
		
		foreach ($this->entities['node'] as $node) {
			if ($node->label() == $title) 
				return $node->id();
		}
		
		return null;
	}
	
	private function mapFileUrisToFids($uris) {
	    if (empty($uris)) return [];
		
		return array_map(
			function($uri) { return $this->mapFileUriToFid($uri); }, 
			$uris
		);
	}
	
	private function mapFileUriToFid($uri) {
	    if (!$uri) return null;
		
		foreach ($this->entities['file'] as $file) {
			if ($file->uri() == $uri) 
				return $uri->id();
		}
		
		return null;
	}
	
}

?>