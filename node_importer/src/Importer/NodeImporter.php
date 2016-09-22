<?php

/**
 * @file
 * Contains \Drupal\node_importer\Importer\NodeImporter.
 */

namespace Drupal\node_importer\Importer;

use \Exception;

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

    function __construct($overwrite = false, $userId) {
    	parent::__construct($overwrite, $userId);
    	
        $this->entities['node'] = []; // [ nid => ..., uuid => ... ]
        $this->entities['file'] = [];
        $this->entities['path'] = [];
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
     *     "uuid"
     * 
     * @return node
     */
    public function createNode($params) {
		if (!$params['title']) throw new Exception('Error: named parameter "title" missing.');
		if (!$params['type']) throw new Exception('Error: named parameter "type" missing.');
		$uuid = $params['uuid'] ? $params['uuid'] : $params['title'];
		
		$this->deleteNodeIfExists($uuid);
		
		$type = $params['type'] ?: 'article';
		if (!$this->contentTypeExists($type)) {
			$this->logWarning("Content type '$type' does not exist in Drupal.");
			return;
		}
		
		$node = Node::create([
			'type'     => $type,
			'title'    => $params['title'],
			'uuid'     => $uuid,
			'langcode' => 'en', // @todo get language from import file
			'status'   => 1,
			'uid'      => $this->userId
		]);
		$node->save();
		
		if (array_key_exists('fields', $params))
			$this->insertFields($node, $params['fields']);
		
		if (array_key_exists('alias', $params))
			$this->addAlias([
				'id'    => $node->id(),
				'alias' => $params['alias']
			]);
			
		$this->entities['node'][] = [ 'nid' => $node->id(), 'uuid' => $uuid ];
		$node = null;
	}
	
	/**
	 * Checks if a content type exists with given id.
	 * 
	 * @param $type content type
	 * 
	 * @return boolean
	 */
	private function contentTypeExists($type) {
		return array_key_exists(
			$type,
			\Drupal::entityManager()->getBundleInfo('node')
		);
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
		
		if (!file_exists(\Drupal::service('file_system')->realpath($drupalUri)))
			throw new Exception('Error: file '. \Drupal::service('file_system')->realpath($drupalUri). ' could not be found.');
		
		$file = File::create([
			'uid'    => $this->userId,
			'uri'    => $drupalUri,
			'status' => 1,
		]);
		$file->save();
		
		$this->entities['file'][] = $file->id();
			
		return $file;
	}
	
	/**
	 * Checks of a node with given uuid already exists
	 * and deletes it if overwrite is true.
	 * 
	 * @param $uuid uuid of the node
	 */
	private function deleteNodeIfExists($uuid) {
		if (!$uuid) throw new Exception('Error: parameter uuid missing.');
		
		if (!empty($ids = $this->searchNodeIdsByUuid($uuid))) {
			if ($this->overwrite) {
				foreach ($ids as $id) {
					\Drupal::service('path.alias_storage')->delete([ 'source' => '/node/'. $id ]);
					Node::load($id)->delete();
				}
			} else {
				throw new Exception(
					"Node with uuid '$uuid' already exists. "
					. 'Tick "overwrite" if you want to replace it and try again.'
				);
			}
		}
	}
	
	/**
	 * Queries the drupal DB with node uuid and returns corresponding ids.
	 * 
	 * @param $uuid uuid
	 * 
	 * @return array of ids
	 */
	protected function searchNodeIdsByUuid($uuid) {
	    if (!$uuid) throw new Exception('Error: parameter $uuid missing');
	    
	    $result = $this->searchEntityIds([
	        'entity_type' => 'node',
	        'uuid'       => $uuid,
	    ]);
	    
	    return $result;
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
			if (!$this->nodeHasField($node, $field['field_name'])) {
				$this->logWarning(
					'field "'. $field['field_name']
					. '" does not exists in "'. $node->bundle(). '".'
				);
				continue;
			}
			
			if (array_key_exists('references', $field) && $field['references']) {
				$this->nodeReferences[$node->id()][$field['field_name']][$field['references']]
					= $field['value'];
			} else {
				if (array_key_exists('entity', $field) && $field['entity'] == 'file') {
					if (array_key_exists('uri', $field['value'])) {
						$file = $this->createFile($field['value']['uri']);
						$field['value']['target_id'] = $file->id();
						unset($file);
					} else {
						foreach ($field['value'] as $value) {
							$file = $this->createFile($value['uri']);
							$value['target_id'] = $file->id();
							unset($file, $value);
						}
					}
				}
				$node->get($field['field_name'])->setValue($field['value']);
			}
			unset($field);
		}
		
		$node->save();
		$node = null;
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
							$entityIds = $this->mapNodeUuidsToNids($entityNames);
							break;
						case 'image':
							throw new Exception('References to images are not implemented.');
							break;
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
	 * Returns an array of nids for a given array of recently created node uuids.
	 * 
	 * @param $uuids array of node uuids
	 * 
	 * @return array of nids
	 */
	private function mapNodeUuidsToNids($uuids) {
		if (empty($uuids)) return [];
		
		return array_map(
			function($uuid) { return $this->mapNodeUuidToNid($uuid); }, 
			$uuids
		);
	}
	
	/**
	 * Returns a nid for a recently created node uuid.
	 * 
	 * @param $uuid node uuid
	 * 
	 * @return {integer} nid
	 */
	private function mapNodeUuidToNid($uuid) {
		if (!$uuid) return null;
		
		foreach ($this->entities['node'] as $node) {
			if ($node['uuid'] == $uuid) 
				return $node['nid'];
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