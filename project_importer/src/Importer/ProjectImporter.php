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

class ProjectImporter {
	private $tagMapper       = []; // [ 'Name1' => 'ID1', 'Name2' => 'ID2', ...]
	private $tagChildParents = []; // [ 'child' => [ 'parent 1', 'parent 2' ], ... ]
	private $entities        = []; // for rolling back on error
	private $overwrite       = false;
	private $counter         = [
		'vocabulary' => 0,
		'tag'        => 0,
		'node'       => 0,
	];
	
	public function ProjectImporter() {}
	
	public function import($fid, $overwrite = false) {
		if ($overwrite) $this->overwrite = true;
		
		try {
			$data = $this->handleJsonFile($fid);
			
			foreach ($data['vocabularies'] as $vocabulary) {
				$this->createTaxonomy($vocabulary);
				$this->setTaxonomyParents();
			}
			
			foreach ($data['projects'] as $project) {
				$this->createProject($project);
			}
			
			drupal_set_message(
				sprintf(
					t('Success! %d vocabularies with %d terms and %d projects imported.'),
					$this->counter['vocabulary'],
					$this->counter['term'],
					$this->counter['node']
				)
			);
		} catch (Exception $e) {
			$message = $this->rollback();
			drupal_set_message(t($e->getMessage()). ' '. t($message), 'error');
		}
		$this->resetCounters();
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
			
			$this->addTagToMapper($tag['name'], $term->id());
			$this->addTagChildParents($tag['name'], $tag['parents']);
			
			array_push($this->entities, $term);
			$this->counter['term']++;
			
			// \Drupal::service('path.alias_storage')->save('/taxonomy/term/' . $term->id(), '/tags/my-tag', 'de');
		}
	}
	
	private function createVocabulary($vid, $name) {
		if (!$vid) throw new Exception('Error: parameter $vid missing');
		if (!$name) throw new Exception('Error: parameter $name missing');
		
		if ($id = $this->searchVocabularyByVid($vid)) {
			if ($this->overwrite) {
				Vocabulary::load($id)->delete();
			} else {
				throw new Exception(
					'Error: vocabulary with vid "'. $vid. '" already exists. '
					. 'Tick "overwrite" if you want to replace it and try again.'
				);
			}
		}
		
		$vocabulary = Vocabulary::create([
			'name'   => $name,
			'weight' => 0,
			'vid'    => $vid
		]);
		$vocabulary->save();
		array_push($this->entities, $vocabulary);
		$this->counter['vocabulary']++;
		
		return $vocabulary;
	}
	
	private function createProject($params) {
		if (!$params['title']) throw new Exception('Error: named parameter "title" missing');

		if (!empty($ids = $this->searchNodesByTitle($params['title']))) {
			if ($this->overwrite) {
				foreach ($ids as $id) {
					Node::load($id)->delete();
				}
			} else {
				throw new Exception(
					'Project with title "'. $params['title']. '" already exists. '
					. 'Tick "overwrite" if you want to replace it and try again.'
				);
			}
		}

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
		
		$this->addAlias([
			'id'    => $node->id(),
			'alias' => $params['alias']
		]);
		array_push($this->entities, $node);
		$this->counter['node']++;
		
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
		array_push($this->entities, $file);
			
		return $file;
	}
	
	private function addAlias($params) {
		if (!$params['id']) throw new Exception('Error: named parameter "id" missing');
		if (!$params['alias']) throw new Exception('Error: named parameter "alias" missing');
		
		\Drupal::service('path.alias_storage')->save(
			'/node/'. $params['id'], 
			'/'. $params['alias'], 
			'de'
		);
	}
	
	private function addTagToMapper($name, $tid) {
		if (!$name) throw new Exception('Error: parameter $name missing');
		if (!$tid) throw new Exception('Error: parameter $tid missing');
		
		$this->tagMapper[$name] = $tid;
	}
	
	private function mapTagNamesToTids($tags) {
		if (empty($tags)) return [];
		
		return array_map(
			function($name) { return $this->tagMapper[$name]; }, 
			$tags
		);
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
	
	private function addTagChildParents($child, $parents) {
		if (empty($parents)) return;
		
		$this->tagChildParents[$child] = $parents;
	}
	
	private function setTaxonomyParents() {
		foreach ($this->tagChildParents as $child => $parents) {
			if (empty($parents)) continue;
			$childEntity = Term::load($this->mapTagNamesToTids([$child])[0]);
			
			$childEntity->parent->setValue($this->mapTagNamesToTids($parents));
			$childEntity->save();
		}
	}
	
	private function handleJsonFile($fid) {
		$json_file = \Drupal\file\Entity\File::load($fid);
		$data = file_get_contents(drupal_realpath($json_file->getFileUri()));
		file_delete($json_file->id());
		
		$data = json_decode($data, TRUE);
		
		if (json_last_error() != 0) throw new Exception('Error: Could not decode the json file.');
		
		return $data;
	}
	
	private function rollback() {
		$message = 'Rolling back... ';
		foreach ($this->entities as $entity) {
			// $message .= 'Entity: '. $entity->label(). ' (ID: '. $entity->id(). ') deleted.';
			$entity->delete();
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
	
	private function resetCounters() {
		$this->counter = [
			'vocabulary' => 0,
			'tag'        => 0,
			'node'       => 0,
		];
	}
}

?>