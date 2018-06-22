<?php

/**
 * @file
 * Contains \Drupal\SOLID\Importer\VocabularyImporter.
 */

namespace Drupal\SOLID\Importer;

use \Exception;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

/**
 * Importer for vocabularies
 * 
 * @author Christoph Beger
 */
class VocabularyImporter extends AbstractImporter {
	
	function __construct($overwrite = false, $userId) {
		parent::__construct($overwrite, $userId);
		
		$this->entities['taxonomy_vocabulary'] = [];
		$this->entities['taxonomy_term'] = [];
	}
	
	public function import($data) {
		throw new Exception('deprecated use of import()');
		if (empty($data)) return;
		
		foreach ($data as $vocabulary) {
			$this->createVocabulary($vocabulary['vid'], $vocabulary['name']);
			$this->createTags($vocabulary['tags']);
			$this->setTagParents($vocabulary['tags']);
		}
		$this->insertEntityReferences();
	}
	
	public function countCreatedVocabularies() {
		return sizeof($this->entities['taxonomy_vocabulary']);
	}
	
	public function countCreatedTags() {
		return sizeof($this->entities['taxonomy_term']);
	}
	
	/**
	 * Creates a vocabulary.
	 * 
	 * @param $vid vid of the vocabulary
	 * @param $name name of the vocabulary
	 */
	public function createVocabulary($vid, $name) {
		if (is_null($vid)) throw new Exception('Error: parameter $vid missing.');
		if (is_null($name)) throw new Exception('Error: parameter $name missing.');
		
		if (!$this->vocabularyExists($vid)) {
			$vocabulary = Vocabulary::create([
				'name'   => $name,
				'weight' => 0,
				'vid'    => $vid
			]);
			$vocabulary->save();
			$this->entities['taxonomy_vocabulary'][] = $vocabulary->id();
			$vocabulary = null;
		} else if ($this->overwrite) {
			$this->clearVocabulary($vid);
		}
	}
	
	/**
	 * Creates a set of Drupal tags for given vocabulary.
	 * Does not add parents to the tags, because they may not exit yet.
	 * 
	 * @param $vid vid of the vocabulary
	 * @param $tags array of tags
	 */
	public function createTags($vid, $tags) {
		if (is_null($vid)) throw new Exception('Error: parameter $vid missing.');
		if (empty($tags)) return;
		
		foreach ($tags as $tag) {
			$tag['vid'] = $vid;
			$term = $this->createTag($tag);
		}
	}
	
	/**
	 * Creates a single Drupal tag for given vocabulary.
	 * Does not add parents to the tags, because they may not exit yet.
	 * 
	 * @param $params the parameters to use for creation (e.g., 'vid' and 'name')
	 */
	public function createTag($params) {
		if (is_null($params['vid'])) throw new Exception('Error: parameter $vid missing.');
		if (empty($params['name']) || empty($params['uuid'])) return;
		
		if (!is_null($tid = $this->searchEntityIdByUuid('taxonomy_term', $params['uuid']))) {
			$term = Term::load($tid);
			$term->setName($params['name']);
			$term->save();
		} else {
			try {
				$term = Term::create([
					'name' => $params['name'],
					'vid'  => $params['vid'],
					'uuid' => $params['uuid']
				]);
				$term->save();
			} catch (Exception $e) {
				$this->logWarning(t($e->getMessage()). " In {$e->getFile()} (line:{$e->getLine()}) ");
			}
		}

		if (array_key_exists('fields', $params))
			$this->insertFields($term, $params['fields']);
			
		$this->entities['taxonomy_term'][] = $term->id();
	}

	/**
	 * Handles all in $entityReferences saved references and inserts them.
	 */
	 public function insertEntityReferences() {
		foreach ($this->entityReferences as $tid => $field) {
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
					$tag = Term::load($tid);
					$tag->get($fieldName)->setValue($entityIds);
					$tag->save();
				}
			}
		}
	}

	/**
	 * Inserts fields into a tag
	 * 
	 * @param $tag drupal tag
	 * @param $fields array of tag fields
	 */
	 private function insertFields($tag, $fields) {
		if (is_null($tag)) throw new Exception('Error: parameter $tag missing');
		if (empty($fields)) return;
		
		foreach ($fields as $field) {
			if ($field == null) continue;
			$fieldName = substr($field['field_name'], 0, self::MAX_FIELDNAME_LENGTH);
			
			if (!$this->entityHasField($tag, $fieldName)) {
				$this->logWarning(
					"field '$fieldName' does not exist in '{$tag->bundle()}'"
				);
				continue;
			}
			
			if (array_key_exists('references', $field)
				&& ($field['references'] == 'taxonomy_term' || $field['references'] == 'node')
			) {
				$this->entityReferences[$tag->id()][$fieldName][$field['references']]
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
					$tag->get($fieldName)->setValue($field['value']);
				}
				if (!is_null($field['value'])) {
					if (is_array($field['value'])) {
						if (!empty($field['value'])
							&& (!array_key_exists('value', $field['value'])
								|| !is_null($field['value']['value'])
							)
						) {
							$tag->get($fieldName)->setValue($field['value']);
						}
					} else {
						$tag->get($fieldName)->setValue($field['value']);
					}
				}

			}
			unset($field);
		}
		
		$tag->save();
		$tag = null;
	}
	
	/**
	 * Checks if a vocabulary with given vid already exists
	 * and deletes all its tags if $this->overwrite is true.
	 * 
	 * @param $vid vid of the vocabulary
	 * 
	 * @return boolean
	 */
	private function clearVocabulary($vid) {
		if (!$this->vocabularyExists($vid) || !$this->overwrite)
			throw new Exception("clearVocabulary() called for non existing vocabulary or overwrite = false");	

		$tids = $this->searchEntityIds([
			'entity_type' => 'taxonomy_term',
			'vid'         => $vid
		]);
		
		if (!empty($tids)) {
			$storage_handler = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
			$terms = $storage_handler->loadMultiple($tids);
			$storage_handler->delete($terms);
				
			$storage_handler = null;
			$terms = null;
			$tids = null;
		}
	}
	
	/**
	 * Checks if a vocabulary with vid exists.
	 * 
	 * @param $vid vid to search for
	 * 
	 * @return boolean
	 */
	private function vocabularyExists($vid) {
		if (is_null($vid)) throw new Exception('Error: parameter $vid missing');

		$array = array_values($this->searchEntityIds([
			'vid'         => $vid,
			'entity_type' => 'taxonomy_vocabulary',
		]));
		$result = !empty($array) && $array[0] ? true : false;
		$array = null;
		
		return $result;
	}
	
	/**
	 * Adds parents to all created tags.
	 * 
	 * @param $uuid uuid
	 * @param $parents all parent tags of the tag specified by uuid
	 */
	public function setTagParents($uuid, $parents) {
		if (is_null($uuid)) throw new Exception('Error: parameter $uuid missing');
		if (empty($parents)) return;
			
		$tagEntity = Term::load($this->searchEntityIdByUuid('taxonomy_term', $uuid));
		$tagEntity->parent->setValue($this->searchEntityIdsByUuids('taxonomy_term', $parents));
		$tagEntity->save();
	}
	
}
 
?>