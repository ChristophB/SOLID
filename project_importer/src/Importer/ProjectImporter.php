<?php

/**
 * @file
 * Contains \Drupal\project_importer\Importer\ProjectImporter.
 */


namespace Drupal\project_importer\Importer;

use Exception;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

class ProjectImporter {
	private $tagChildParents   = []; // [ 'child' => [ 'parent 1', 'parent 2' ], ... ]
	private $entities          = [ 'tag' => [], 'vocabulary' => [], 'node' => [], 'file' => [] ]; // for rolling back on error
	private $projectReferences = []; // [ 'ProjectID' => [ 'field_name' => [ 'refEntityType' => [ 'EntityTitle', ... ] ] ] ]
	private $overwrite         = false;
	
	public function ProjectImporter() {}
	
	public function import($fid, $overwrite = false) {
		if ($overwrite) $this->overwrite = true;

		try {
			function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    			throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
			}
			set_error_handler('exception_error_handler');
		
			$data = $this->handleJsonFile($fid);
			
			foreach ($data['vocabularies'] as $vocabulary) {
				$this->createTaxonomy($vocabulary);
				$this->setTaxonomyParents();
			}
			
			foreach ($data['projects'] as $project) {
				$this->createProject($project);
			}
			$this->insertProjectReferences();
			
			drupal_set_message(
				sprintf(
					t('Success! %d vocabularies with %d terms and %d projects imported.'),
					sizeof($this->entities['vocabulary']),
					sizeof($this->entities['tag']),
					sizeof($this->entities['node'])
				)
			);
		} catch (Exception $e) {
			$message = $this->rollback();
			drupal_set_message(t($e->getMessage()). ' '. t($message), 'error');
		}
	}
	
	private function createTaxonomy($params) {
		if (!$params['vid']) throw new Exception('Error: named parameter "vid" missing');
		if (!$params['name']) throw new Exception('Error: named parameter "name" missing');
		if (empty($params['tags'])) throw new Exception('Error: named parameter "tags" missing');
	
		$vocabulary = $this->createVocabulary($params['vid'], $params['name']);
		
		foreach ($params['tags'] as $tag) {
			$term = Term::create([
				'name'   => $tag['name'],
				'vid'    => $vocabulary->id(),
			]);
			$term->save();
			
			$this->addTagChildParents($tag['name'], $tag['parents']);
			
			array_push($this->entities['tag'], $term);
			
			// \Drupal::service('path.alias_storage')->save('/taxonomy/term/' . $term->id(), '/tags/my-tag', 'de');
		}
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
		
		return $vocabulary;
	}
	
	private function createProject($params) {
		if (!$params['title']) throw new Exception('Error: named parameter "title" missing');

		$this->deleteProjectIfExists($params['title']);

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
			'field_tags'  => $this->mapTagNamesToTids($params['tags']),
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
	
	private function insertCustomFields($node, $customFields) {
		if (empty($customFields)) return;
		
		foreach ($customFields as $fieldContent) {
			if ($refEntityType = $fieldContent['references']) {
				$this->projectReferences[$node->id()][$fieldContent['field_name']][$refEntityType]
					= $fieldContent['value'];
			} else {
				$node->get($fieldContent['field_name'])->setValue($fieldContent['value']);
			}
		}
	}
	
	private function insertProjectReferences() {
		foreach ($this->projectReferences as $pid => $field) {
			foreach ($field as $fieldName => $reference) { // assumption: only one entitytype per field
				foreach ($reference as $entityType => $entityNames) {
					$entityIds = [];
					
					switch ($entityType) {
						case 'tag': 
							$entityIds = $this->mapTagNamesToTids($entityNames);
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
	
	private function addAlias($params) {
		if (!$params['id']) throw new Exception('Error: named parameter "id" missing');
		if (!$params['alias']) return;
		
		\Drupal::service('path.alias_storage')->save(
			'/node/'. $params['id'], 
			'/'. $params['alias'], 
			'de'
		);
	}
	
	private function mapTagNamesToTids($tags) {
		if (empty($tags)) return [];
		
		return array_map(
			function($name) { return $this->mapTagNameToTid($name); }, 
			$tags
		);
	}
	
	private function mapTagNameToTid($name) {
		if (!$name) return null;
		
		foreach ($this->entities['tag'] as $tag) {
			if ($tag->label() == $name)
				return $tag->id();
		}
		
		return null;
	}
	
	private function constructFieldImage($img) {
		if (!$img) return [];
		
		$file = $this->createFile($img['uri']);
		
		array_push($this->entities['file'], $file);
		
		return [
			'target_id' => $file->id(),
			'alt'       => $img['alt'],
			'title'     => $img['title'],
		];
	}
	
	private function addTagChildParents($child, $parents) {
		if (empty($parents)) return;
		
		$this->tagChildParents[$child] = $parents;
	}
	
	private function setTaxonomyParents() {
		foreach ($this->tagChildParents as $child => $parents) {
			if (empty($parents)) continue;
			$childEntity = Term::load($this->mapTagNameToTid($child));
			
			$childEntity->parent->setValue($this->mapTagNamesToTids($parents));
			$childEntity->save();
		}
	}
	
	private function handleJsonFile($fid) {
		$json_file = File::load($fid);
		$data = file_get_contents(drupal_realpath($json_file->getFileUri()));
		// file_delete($json_file->id());
		
		$data = json_decode($data, TRUE);
		
		if (json_last_error() != 0) throw new Exception('Error: Could not decode the json file.');
		
		return $data;
	}
	
	private function rollback() {
		$message = 'Rolling back... ';
		foreach ($this->entities as $type => $entities) {
			foreach ($entities as $entity) {
				$entity->delete();
			}
		}
		return $message;
	}
	
	private function searchNodesByTitle($title) {
		if (!$title) throw new Exception('Error: parameter $title missing');
		
		return $this->searchEntity([
			'title'       => $title,
			'entity_type' => 'node',
		]);
	}
	
	private function searchVocabularyByVid($vid) {
		if (!$vid) throw new Exception('Error: parameter $vid missing');
		
		return array_values($this->searchEntity([
			'vid'         => $vid,
			'entity_type' => 'taxonomy_vocabulary',
		]))[0];
	}
	
	private function searchEntity($params) {
		if (!$params['entity_type']) throw new Exception('Error: named parameter "entity_type" missing');
		
		$query = \Drupal::entityQuery($params['entity_type']);
		
		foreach ($params as $key => $value) {
			if ($key == 'entity_type') continue;
			$query->condition($key, $value);
		}
		
		return $query->execute();
	}
	
	private function deleteProjectIfExists($title) {
		if (!empty($ids = $this->searchNodesByTitle($title))) {
			if ($this->overwrite) {
				foreach ($ids as $id) {
					Node::load($id)->delete();
				}
			} else {
				throw new Exception(
					"Project with title '$title' already exists. "
					. 'Tick "overwrite" if you want to replace it and try again.'
				);
			}
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
	
}

?>