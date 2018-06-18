<?php

/**
 * @file
 * Contains \Drupal\SOLID\Importer\AbstractImporter.
 */

namespace Drupal\SOLID\Importer;

use \Exception;

/**
 * This abstract class declares all functions which are required for an Importer.
 * 
 * @author Christoph Beger
 */
abstract class AbstractImporter {
	const MAX_FIELDNAME_LENGTH = 32;

    /**
     * @var array $entities array of created entities, used to rollback.
     * @var boolean $overwrite importer overwrites existing nodes/vocabularies if true.
     */
    protected $entities  = [];
    protected $overwrite = false;
    protected $warnings  = [];
	protected $userId;
	
	/**
	 * @var $entityReferences array
	 *   Stores references between entities,
	 *   to process them after all entities are created.
	 *   [ id => [ field_name => [ refEntityType => [ EntityTitle, ... ] ], ... ], ... ]
	 */
	protected $entityReferences = [];
    
    public function __construct($overwrite = false, $userId) {
    	$this->userId = $userId;
    	if ($overwrite) $this->overwrite = true;
    }
    
    /**
     * Imports given data php object.
     * 
     * @param $data php object with the data to be imported
     * @param $overwrite specifies overwrite, default: false
     */
    abstract public function import($data);
    
    /**
     * Deletes all created entities.
     */
    public function rollback() {
         foreach ($this->entities as $type => $entities) {
         	$entity_manager = \Drupal::entityManager();
			foreach ($entities as $entity) {
				switch ($type) {
					case 'path':
						\Drupal::service('path.alias_storage')->delete([ 'pid' => $entity ]);
						break;
					case 'node': 
						$drupal_entity = $entity_manager->getStorage($type)->load($entity['nid']);
						if (!is_null($drupal_entity)) $drupal_entity->delete();
						break;
					default:
						$drupal_entity = $entity_manager->getStorage($type)->load($entity);
						if (!is_null($drupal_entity)) $drupal_entity->delete();
				}
			}
		}
     }
    
    /**
     * Returns an array of IDs for a given entity_type and additional restrictions.
     * 
     * @param $params array of named parameters
     *   "entity_type" is required.
     *   All additional fields are used to query the Drupal DB.
     * 
     * @return array of IDs
     */
    protected function searchEntityIds($params) {
		if (is_null($params['entity_type'])) throw new Exception('Error: named parameter "entity_type" missing');
		$query
			= \Drupal::entityQuery($params['entity_type'])
			->addMetadata('account', user_load($this->userId));
		
		foreach ($params as $key => $value) {
			if ($key === 'entity_type') continue;
			$query->condition($key, $value);
			unset($key, $value);
		}
		$result = $query->execute();
		$query = null;
		
		return $result;
	}

	/**
	 * Checks if entity has field with given name.
	 * 
	 * @param $entity drupal entity
	 * @param $fieldName name to check for
	 * 
	 * @return boolean
	 */
	 protected function entityHasField($entity, $fieldName) {
		if (is_null($entity)) throw new Exception('Error: parameter $entity missing.');
		if (is_null($fieldName)) throw new Exception('Error: parameter $fieldName missing.');
		
		try {
			$entity->get($fieldName);	
		} catch (Exception $e) {
			return false;
		}
		return true;
	}

	/**
	 * Creates a Drupal file for uri.
	 * 
	 * @param $uri uri representation of the file
	 * 
	 * @return file
	 */
	 protected function createFile($uri) {
		if (empty($uri)) throw new Exception('Error: parameter $uri missing.');
		$drupalUri = file_default_scheme(). "://$uri";
		
		// @todo: does not work in script
		if (!file_exists($drupalUri))
			$this->logWarning("File '$drupalUri' does not exist, but URI entry inserted into DB. Upload the file manually to the server!");
		
		if ($fid = $this->searchFileByUri($drupalUri)) {
			$this->logNotice("Found file $fid for uri '$drupalUri'.");
			return File::load($fid);
		}
		
		$file = File::create([
			'uid'    => $this->userId,
			'uri'    => $drupalUri,
			'status' => 1,
		]);
		$file->save();
		$this->entities['file'][] = $file->id();
			
		return $file;
	}

	protected function searchFileByUri($uri) {
		if (empty($uri)) throw new Exception('Error: parameter $uri missing.');
		
		$result = $this->searchEntityIds([
			'entity_type' => 'file',
			'uri'         => $uri
		]);

		return $result ? array_values($result)[0] : null;
	}
	
	/**
	 * Returns the tid for a vid and tag name.
	 * 
	 * @param $vid vid of the vocabulary to search in
	 * @param $name name of the tag
	 * 
	 * @return integer tid
	 */
	protected function searchTagIdByName($vid, $name) {
	    if (is_null($vid)) throw new Exception('Error: parameter $vid missing');
	    if (is_null($name)) throw new Exception('Error: parameter $name missing');
	    
	    $result = $this->searchEntityIds([
	        'entity_type' => 'taxonomy_term',
	        'vid'         => $vid,
	        'name'        => $name
	    ]);
	    
	    return $result ? array_values($result)[0] : null;
	}

	/**
	 * Queries the drupal DB with node uuid and returns corresponding id.
	 * 
	 * @param $uuid uuid
	 * 
	 * @return id
	 */
	 public function searchNodeIdByUuid($uuid) {
	    if (empty($uuid)) throw new Exception('Error: parameter $uuid missing');
	    
	    $result = array_values($this->searchEntityIds([
	        'entity_type' => 'node',
	        'uuid'        => $uuid,
	    ]));
	    
	    return empty($result) ? null : $result[0];
	}

	/**
	 * Returns an array of nids for a given array of recently created node uuids.
	 * 
	 * @param $uuids array of node uuids
	 * 
	 * @return array of nids
	 */
	 protected function mapNodeUuidsToNids($uuids) {
		if (empty($uuids)) return [];
		
		return array_map(
			function($uuid) { return $this->searchNodeIdByUuid($uuid); }, 
			$uuids
		);
	}
	
	/**
	 * Returns an array of tids for a set of tags.
	 * Each tag is represented by an array [vid, name].
	 * 
	 * @param $tag array of tag representations
	 * 
	 * @return array of tids
	 */
	protected function searchTagIdsByNames($tags) {
		if (empty($tags)) return [];
		
		return array_map(
			function($tag) {
				return $this->searchTagIdByName($tag['vid'], $tag['name']);
			}, $tags
		);
	}
	
	protected function logWarning($msg) {
		if (!in_array($msg, $this->warnings)) {
			\Drupal::logger('SOLID')->warning($msg);
			print date('H:i:s', time()). "> Warning: $msg\n";
			array_push($this->warnings, $msg);
		}
	}
	
	protected function logNotice($msg) {
		\Drupal::logger('SOLID')->notice($msg);
		print date('H:i:s', time()). "> $msg\n";
	}
	
}
 
?>