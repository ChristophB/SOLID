<?php

namespace Drupal\project_importer\Controller;

use Exception;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;

class ProjectImporterController {
	private static $userId;
	private static $tagMapper       = []; // [ 'Name1' => 'ID1', 'Name2' => 'ID2', ...]
	private static $tagChildParents = []; // [ 'child' => [ 'parent 1', 'parent 2' ], ... ]
	
	public function import($fid) {
		try {
			self::$userId = \Drupal::currentUser()->id();
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
			drupal_set_message('Error! '. $e->getMessage(), 'error');
		}
	}
	
	private function createTaxonomy($params) {
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
			
			// \Drupal::service('path.alias_storage')->save('/taxonomy/term/' . $term->id(), '/tags/my-tag', 'de');
		}
	}
	
	private function createVocabulary($vid, $name) {
		if (!$vid) throw new Exception('Error: parameter $vid missing');
		if (!$name) throw new Exception('Error: parameter $name missing');
		
		$vocabulary = Vocabulary::create([
			'name' => $name,
			'weight' => 0,
			'vid' => $vid
		]);
		$vocabulary->save();
		
		return $vocabulary;
	}
	
	private function createProject($params) {
		if (!$params['title']) throw new Exception('Error: named parameter "title" missing');

		$node = Node::create([
			'type'     => 'article',
			'title'    => $params['title'],
			'langcode' => 'de',
			'status'   => 1,
			'uid'      => self::$userId,
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
		
		return $node;
	}
	
	private function createFile($uri) {
		if (!$uri) throw new Exception('Error: parameter $uri missing');
		
		$file = File::create([
			'uid'    => self::$userId,
			'uri'    => $uri,
			'status' => 1,
		]);
		$file->save();
			
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
		
		self::$tagMapper[$name] = $tid;
	}
	
	private function mapTagNamesToTids($tags) {
		if (empty($tags)) return [];
		
		return array_map(
			function($name) { return self::$tagMapper[$name]; }, 
			$tags
		);
	}
	
	private function constructFieldImage($img) {
		if (!$img) return [];
		
		$file = self::createFile($img['uri']);
		
		return [
			'target_id' => $file->id(),
			'alt'       => $img['alt'],
			'title'     => $img['title'],
		];
	}
	
	private function addTagChildParents($child, $parents) {
		if (empty($parents)) return;
		
		self::$tagChildParents[$child] = $parents;
	}
	
	private function setTaxonomyParents() {
		foreach (self::$tagChildParents as $child => $parents) {
			if (empty($parents)) next;
			$childEntity = entity_load('taxonomy_term', self::mapTagNamesToTids([$child])[0]);
			
			$childEntity->parent->setValue(self::mapTagNamesToTids($parents));
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
	
}