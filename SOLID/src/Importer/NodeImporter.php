<?php

/**
 * @file
 * Contains \Drupal\SOLID\Importer\NodeImporter.
 */

namespace Drupal\SOLID\Importer;

use \Exception;

use Drupal\node\Entity\Node;
use Drupal\core\StreamWrapper\StreamWrapperManager;

/**
 * Importer for nodes.
 * 
 * @author Christoph Beger
 */
class NodeImporter extends AbstractImporter {

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
	    $this->insertEntityReferences();
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
     *     "title",
     *     "type" corresponds to bundle (e.g. "article" or "page")
     *   optional:
     *     "fields" contains the fields with names and values,
     *     "alias", "uuid"
     * 
     * @return node
     */
    public function createNode($params) {
		if (is_null($params['title'])) throw new Exception('Error: named parameter "title" missing.');
		if (is_null($params['type'])) throw new Exception('Error: named parameter "type" missing.');
		$uuid = $params['uuid'] ?: $params['title'];
		
		$type = $params['type'] ?: 'article';
		if (!$this->contentTypeExists($type)) {
			$this->logWarning("Content type '$type' does not exist in Drupal.");
			return;
		}
		
		$node;
		if (!is_null($id = $this->searchEntityIdByUuid('node', $uuid))) {
			$node = Node::load($id);
			$node->setNewRevision(true);
			$node->setRevisionLogMessage('Incrementally updated at '. date('Y-m-d h:i', time()));
			$node->setTitle($params['title']);
			$node->save();
		} else {
			$node = Node::create([
				'type'     => $type,
				'title'    => $params['title'],
				'uuid'     => $uuid,
				'langcode' => 'en', // @todo get language from import file
				'status'   => 1,
				'uid'      => $this->userId
			]);
			$node->save();
		}
		
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
	 * Inserts fields into a node
	 * 
	 * @param $node drupal node
	 * @param $fields array of node fields
	 */
	private function insertFields($node, $fields) {
		if (is_null($node)) throw new Exception('Error: parameter $node missing');
		if (empty($fields)) return;
		
		foreach ($fields as $field) {
			if ($field == null) continue;
			$fieldName = substr($field['field_name'], 0, self::MAX_FIELDNAME_LENGTH);
			
			if (!$this->entityHasField($node, $fieldName)) {
				$this->logWarning(
					"field '$fieldName' does not exist in '{$node->bundle()}'"
				);
				continue;
			}
			
			if (array_key_exists('references', $field)
				&& ($field['references'] == 'taxonomy_term' || $field['references'] == 'node')
			) {
				$this->entityReferences[$node->id()][$fieldName][$field['references']]
					= $field['value'];
			} else {
				if (array_key_exists('references', $field) && $field['references'] == 'file') {
					if (array_key_exists('uri', $field['value'])) {
						$file = $this->createFile($field['value']['uri']);
						$field['value']['target_id'] = $file->id();
						unset($file);
					} else {
						for ($i = 0; $i < sizeof($field['value']); $i++) {
							$file = $this->createFile($field['value'][$i]['uri']);
							$field['value'][$i]['target_id'] = $file->id();
							unset($file);
						}
					}
				}
				if (array_key_exists('value', $field) && !is_null($field['value'])
					&& (
						!is_array($field['value'])
						|| (array_key_exists('value', $field['value']) && !is_null($field['value']['value']))
					)
				) {
					$node->get($fieldName)->setValue($field['value']);
				}
				if (!is_null($field['value'])) {
					if (is_array($field['value'])) {
                        if (!empty($field['value'])
                    	    && (!array_key_exists('value', $field['value'])
                        	    || !is_null($field['value']['value'])
                            )
                        ) {
                        	$node->get($fieldName)->setValue($field['value']);
                        }
                    } else {
                        $node->get($fieldName)->setValue($field['value']);
                    }
                }

			}
			unset($field);
		}
		
		$node->save();
		$node = null;
	}
	
	/**
	 * Adds an alias to a node.
	 * 
	 * @param $params array of parameters
	 *   "id" id of the node (required)
	 *   "alias" alias to be inserted (optional)
	 */
	private function addAlias($params) {
		if (empty($params['id'])) throw new Exception('Error: named parameter "id" missing.');
		if (empty($params['alias'])) return;
		
		\Drupal::service('path.alias_storage')->delete([ 'alias' => "/$params[alias]" ]);
		$path = \Drupal::service('path.alias_storage')->save(
			"/node/$params[id]",
			"/$params[alias]",
			'en'
		);
		
		$this->entities['path'][] = $path['pid'];
	}
	
	/**
	 * Handles all in $entityReferences saved references and inserts them.
	 */
	public function insertEntityReferences() {
		foreach ($this->entityReferences as $nid => $field) {
			foreach ($field as $fieldName => $reference) { // assumption: only one entitytype per field
				foreach ($reference as $entityType => $entityNames) {
					$entityIds = [];
					
					switch ($entityType) {
						case 'taxonomy_term': 
							$entityIds = $this->searchEntityIdsByUuids('taxonomy_term', $entityNames);
							break;
						case 'node':
							$entityIds = $this->searchEntityIdsByUuids('node', $entityNames);
							break;
						default:
							throw new Exception(
								"Error: not supported entity type '$entityType' in reference found."
							);
					}
					$node = Node::load($nid);
					$node->get($fieldName)->setValue($entityIds);
					$node->save();
				}
			}
		}
	}
	
}

?>