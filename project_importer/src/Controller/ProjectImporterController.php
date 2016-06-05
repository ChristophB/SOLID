<?php

namespace Drupal\project_importer\Controller;

use Exception;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;

class ProjectImporterController {
	private static $tagMapper       = []; // [ 'Name1' => 'ID1', 'Name2' => 'ID2', ...]
	private static $tagChildParents = []; // [ 'child' => [ 'parent 1', 'parent 2' ], ... ]
	private static $entities        = []; // for rolling back on error
	private static $overwrite       = false;
	
	
	public static function import($fid, $overwrite = false) {
		if ($overwrite) self::$overwrite = true;
		
		try {
			$data = self::handleJsonFile($fid);
			
			foreach ($data['vocabularies'] as $vocabulary) {
				self::createTaxonomy($vocabulary);
				self::setTaxonomyParents();
			}
			
			foreach ($data['projects'] as $project) {
				self::createProject($project);
			}
			
			drupal_set_message('Success! All vocabularies and projects are imported.');
		} catch (Exception $e) {
			$message = self::rollback();
			drupal_set_message('Error! '. $e->getMessage(). ' '. $message, 'error');
		}
	}
	
	private static function createTaxonomy($params) {
		if (!$params['vid']) throw new Exception('Error: named parameter "vid" missing');
		if (!$params['name']) throw new Exception('Error: named parameter "name" missing');
		if (empty($params['tags'])) throw new Exception('Error: named parameter "tags" missing');
	 
		$vocabulary = self::createVocabulary($params['vid'], $params['name']);
		
		foreach ($params['tags'] as $tag) {
			$term = Term::create([
				'name'   => $tag['name'],
				'vid'    => $vocabulary->id(),
			]);
			$term->save();
			
			self::addTagToMapper($tag['name'], $term->id());
			self::addTagChildParents($tag['name'], $tag['parents']);
			
			array_push(self::$entities, $term);
			
			// \Drupal::service('path.alias_storage')->save('/taxonomy/term/' . $term->id(), '/tags/my-tag', 'de');
		}
	}
	
	private static function createVocabulary($vid, $name) {
		if (!$vid) throw new Exception('Error: parameter $vid missing');
		if (!$name) throw new Exception('Error: parameter $name missing');
		
		if ($id = self::searchVocabularyByVid($vid)) {
			if (self::$overwrite) {
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
		array_push(self::$entities, $vocabulary);
		
		return $vocabulary;
	}
	
	private static function createProject($params) {
		if (!$params['title']) throw new Exception('Error: named parameter "title" missing');

		if (!empty($ids = self::searchNodesByTitle($params['title']))) {
			if (self::$overwrite) {
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
			'field_tags'  => self::mapTagNamesToTids($params['tags']),
			'field_image' => self::constructFieldImage($params['img']),
		]);
		$node->save();
		
		self::addAlias([
			'id'    => $node->id(),
			'alias' => $params['alias']
		]);
		array_push(self::$entities, $node);
		
		return $node;
	}
	
	private static function createFile($uri) {
		if (!$uri) throw new Exception('Error: parameter $uri missing');
		
		$file = File::create([
			'uid'    => \Drupal::currentUser()->id(),
			'uri'    => $uri,
			'status' => 1,
		]);
		$file->save();
		array_push(self::$entities, $file);
			
		return $file;
	}
	
	private static function addAlias($params) {
		if (!$params['id']) throw new Exception('Error: named parameter "id" missing');
		if (!$params['alias']) throw new Exception('Error: named parameter "alias" missing');
		
		\Drupal::service('path.alias_storage')->save(
			'/node/'. $params['id'], 
			'/'. $params['alias'], 
			'de'
		);
	}
	
	private static function addTagToMapper($name, $tid) {
		if (!$name) throw new Exception('Error: parameter $name missing');
		if (!$tid) throw new Exception('Error: parameter $tid missing');
		
		self::$tagMapper[$name] = $tid;
	}
	
	private static function mapTagNamesToTids($tags) {
		if (empty($tags)) return [];
		
		return array_map(
			function($name) { return self::$tagMapper[$name]; }, 
			$tags
		);
	}
	
	private static function constructFieldImage($img) {
		if (!$img) return [];
		
		$file = self::createFile($img['uri']);
		
		return [
			'target_id' => $file->id(),
			'alt'       => $img['alt'],
			'title'     => $img['title'],
		];
	}
	
	private static function addTagChildParents($child, $parents) {
		if (empty($parents)) return;
		
		self::$tagChildParents[$child] = $parents;
	}
	
	private static function setTaxonomyParents() {
		foreach (self::$tagChildParents as $child => $parents) {
			if (empty($parents)) continue;
			$childEntity = Term::load(self::mapTagNamesToTids([$child])[0]);
			
			$childEntity->parent->setValue(self::mapTagNamesToTids($parents));
			$childEntity->save();
		}
	}
	
	private static function handleJsonFile($fid) {
		$json_file = \Drupal\file\Entity\File::load($fid);
		$data = file_get_contents(drupal_realpath($json_file->getFileUri()));
		file_delete($json_file->id());
		
		$data = json_decode($data, TRUE);
		
		if (json_last_error() != 0) throw new Exception('Error: Could not decode the json file.');
		
		return $data;
	}
	
	private static function rollback() {
		$message = 'Rolling back... ';
		foreach (self::$entities as $entity) {
			// $message .= 'Entity: '. $entity->label(). ' (ID: '. $entity->id(). ') deleted.';
			$entity->delete();
		}
		return $message;
	}
	
	private static function searchNodesByTitle($title) {
		if (!$title) throw new Exception('Error: parameter $title missing');
		
		return self::searchEntity([
			'title'       => $title,
			'entity_type' => 'node',
		]);
	}
	
	private static function searchVocabularyByVid($vid) {
		if (!$vid) throw new Exception('Error: parameter $vid missing');
		
		return array_values(self::searchEntity([
			'vid'          => $vid,
			'entity_type' => 'taxonomy_vocabulary',
		]))[0];
	}
	
	private static function searchEntity($params) {
		if (!$params['entity_type']) throw new Exception('Error: named parameter "entity_type" missing');
		
		$query = \Drupal::entityQuery($params['entity_type']);
		
		foreach ($params as $key => $value) {
			if ($key == 'entity_type') continue;
			$query->condition($key, $value);
		}
		
		return $query->execute();
	}
}

?>