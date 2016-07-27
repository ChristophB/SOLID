<?php

/**
 * @file
 * Contains \Drupal\node_importer\Importer\NodeImporter.
 */

namespace Drupal\node_importer\Importer;

use Exception;

use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;

/**
 * Importer for nodes.
 * 
 * @author Christoph Beger
 */
class NodeImporter extends AbstractImporter {
	/**
	 * @var $nodeReferences array
	 *   Stores references between nodes and other entities,
	 *   to process them after all entities are created.
	 *   [ nid => [ field_name => [ refEntityType => [ EntityTitle, ... ] ], ... ], ... ]
	 */
    private $nodeReferences = [];

    function __construct($overwrite = false) {
        $this->entities['node'] = [];
        $this->entities['file'] = [];
        $this->entities['path'] = [];
        
        if ($overwrite) $this->overwrite = true;
    }
    
    public function import($data) {
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
    
    /**
     * Creates a node for given parameters.
     * 
     * @param $params array of parameters
     *   required:
     *     "title"
     *     "type" corresponds to bundle (e.g. "article" or "page")
     *   optional:
     *     "fields" contains the fields with names and values
     *     "alias"
     * 
     * @return node
     */
    public function createNode($params) {
		if (!$params['title']) throw new Exception('Error: named parameter "title" missing.');
		if (!$params['type']) throw new Exception('Error: named parameter "type" missing.');

		$this->deleteNodeIfExists($params['title']);
		
		$node = Node::create([
			'type'     => $params['type'] ?: 'article',
			'title'    => $params['title'],
			'langcode' => 'en', // @todo get language from import file
			'status'   => 1,
			'uid'      => \Drupal::currentUser()->id()
		]);
		$node->save();
		
		if (array_key_exists('fields', $params))
			$this->insertFields($node, $params['fields']);
			
		if (array_key_exists('alias', $params))
			$this->addAlias([
				'id'    => $node->id(),
				'alias' => $params['alias']
			]);
			
		$this->entities['node'][] = $node->id();
		
		return $node;
	}
	
	/**
	 * Creates a Drupal file for uri.
	 * 
	 * @param $uri uri representation of the file
	 * 
	 * @return file
	 */
	private function createFile($uri) {
		if (!$uri) throw new Exception('Error: parameter $uri missing.');
		$drupalUri = file_default_scheme(). '://'. $uri;
		
		if (!file_exists(drupal_realpath($drupalUri)))
			throw new Exception('Error: file '. drupal_realpath($drupalUri). ' could not be found.');
		
		$file = File::create([
			'uid'    => \Drupal::currentUser()->id(),
			'uri'    => $drupalUri,
			'status' => 1,
		]);
		$file->save();
		
		$this->entities['file'][] = $file->id();
			
		return $file;
	}
	
	/**
	 * Checks of a node with given title already exists
	 * and deletes it if overwrite is true.
	 * 
	 * @param $title title of the node
	 */
	private function deleteNodeIfExists($title) {
		if (!$title) throw new Exception('Error: parameter title missing.');
		
		if (!empty($ids = $this->searchNodesByTitle($title))) {
			if ($this->overwrite) {
				foreach ($ids as $id) {
					\Drupal::service('path.alias_storage')->delete([ 'source' => '/node/'. $id ]);
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
	
	/**
	 * Returns all Drupal node nids with given title.
	 * 
	 * @param $title title of the node
	 * 
	 * @return array of nids
	 */
	private function searchNodesByTitle($title) {
		if (!$title) throw new Exception('Error: parameter $title missing.');
		
		return $this->searchEntityIds([
			'title'       => $title,
			'entity_type' => 'node',
		]);
	}
	
	/**
	 * 
	 * 
	 * @param $node drupal node
	 * @param $fields array of node fields
	 */
	private function insertFields($node, $fields) {
		if (!$node) throw new Exception('Error: parameter $node missing');
		if (empty($fields)) return;
		
		foreach ($fields as $field) {
			if (!$this->nodeHasField($node, $field['field_name']))
				throw new Exception(
					'Error: field "'. $field['field_name']
					. '" does not exists in "'. $node->bundle(). '".'
				);
			
			if (array_key_exists('references', $field) && $field['references']) {
				$this->nodeReferences[$node->id()][$field['field_name']][$field['references']]
					= $field['value'];
			} else {
				if (array_key_exists('entity', $field) && $field['entity'] == 'file') {
					if (array_key_exists('uri', $field['value'])) {
						$file = $this->createFile($field['value']['uri']);
						$field['value']['target_id'] = $file->id();
					} else {
						foreach ($field['value'] as $value) {
							$file = $this->createFile($value['uri']);
							$value['target_id'] = $file->id();
						}
					}
				}
				$node->get($field['field_name'])->setValue($field['value']);
			}
		}
		
		$node->save();
	}
	
	/**
	 * Checks if node has field with given name.
	 * 
	 * @param $node drupal node
	 * @param $fieldName name to check for
	 * 
	 * @return boolean
	 */
	private function nodeHasField($node, $fieldName) {
		if (!$node) throw new Exception('Error: parameter $node missing.');
		if (!$fieldName) throw new Exception('Error: parameter $fieldName missing.');
		
		try {
			$node->get($fieldName);	
		} catch (Exception $e) {
			return false;
		}
		return true;
	}
	
	/**
	 * Adds an alias to a node.
	 * 
	 * @param $params array of parameters
	 *   "id" id of the node (required)
	 *   "alias" alias to be inserted (optional)
	 */
	private function addAlias($params) {
		if (!$params['id']) throw new Exception('Error: named parameter "id" missing.');
		if (!$params['alias']) return;
		
		$path = \Drupal::service('path.alias_storage')->save(
			'/node/'. $params['id'], 
			'/'. $params['alias'], 
			'en'
		);
		
		$this->entities['path'][] = $path['pid'];
	}
	
	/**
	 * Handles all in $nodeReferences saved references and inserts them.
	 */
	public function insertNodeReferences() {
		foreach ($this->nodeReferences as $pid => $field) {
			foreach ($field as $fieldName => $reference) { // assumption: only one entitytype per field
				foreach ($reference as $entityType => $entityNames) {
					$entityIds = [];
					
					switch ($entityType) {
						case 'taxonomy_term': 
							$entityIds = $this->searchTagIdsByNames($entityNames);
							break;
						case 'node':
							$entityIds = $this->mapNodeTitlesToNids($entityNames);
							break;
						case 'image':
						case 'file':
							$entityIds = $this->mapFileUrisToFids($entityNames);
							break;
						default:
							throw new Exception(
								'Error: not supported entity type "'
								. $entityType. '" in reference found.'
							);
					}
					$node = Node::load($pid);
					$node->get($fieldName)->setValue($entityIds);
					$node->save();
				}
			}
		}
	}
	
	/**
	 * Returns an array of nids for a given array of recently created node titles.
	 * 
	 * @param $titles array of node titles
	 * 
	 * @return array of nids
	 */
	private function mapNodeTitlesToNids($titles) {
		if (empty($titles)) return [];
		
		return array_map(
			function($title) { return $this->mapNodeTitleToNid($title); }, 
			$titles
		);
	}
	
	/**
	 * Returns a nid for a recently created node title.
	 * 
	 * @param $title node title
	 * 
	 * @return integer nid
	 */
	private function mapNodeTitleToNid($title) {
		if (!$title) return null;
		
		foreach ($this->entities['node'] as $nid) {
			$node = Node::load($nid);
			if ($node->label() == $title) 
				return $node->id();
		}
		
		return null;
	}
	
	/**
	 * Returns array of fids for array of recently created file uris.
	 * 
	 * @param $uris array of file uris
	 * 
	 * @return array of fids
	 */
	private function mapFileUrisToFids($uris) {
	    if (empty($uris)) return [];
		
		return array_map(
			function($uri) { return $this->mapFileUriToFid($uri); }, 
			$uris
		);
	}
	
	/**
	 * Returns fid for recently created file uri.
	 * 
	 * @param $uri uri of the file
	 * 
	 * @return integer fid
	 */
	private function mapFileUriToFid($uri) {
	    if (!$uri) return null;
		
		foreach ($this->entities['file'] as $fid) {
			$file = File::load($fid);
			if ($file->uri() == $uri) 
				return $uri->id();
		}
		
		return null;
	}
	
}

?>