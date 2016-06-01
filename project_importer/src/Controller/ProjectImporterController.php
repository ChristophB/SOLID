<?php

namespace Drupal\project_importer\Controller;

use Exception;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;

class ProjectImporterController {
	const VOCABULARY = 'Importiert mit Modul';
	const VID        = '0815';
	
	const PRJ_TITLE     = 'Beispiel-Projekt';
	const PRJ_CONTENT   = '<b>Test Inhalt</b>';
	const PRJ_SUMMARY   = 'Test Summary';
	const PRJ_ALIAS     = 'test_alias';
	const PRJ_IMG_URI   = 'public://page/Chrysanthemum.jpg';
	const PRJ_IMG_ALT   = 'Image Alt';
	const PRJ_IMG_TITLE = 'Image Title';
	
	private static $userId;
	private static $tagMapper       = []; // [ 'Name1' => 'ID1', 'Name2' => 'ID2', ...]
	private static $tagChildParents = []; // [ 'child' => [ 'parent 1', 'parent 2' ], ... ]
	
	public function import() {
		self::$userId = 1;
		try {
			self::createTaxonomy(
			    self::VID,
			    self::VOCABULARY, 
			    [ 
			    	[ 'name' => 'test 1' ], 
			    	[ 'name' => 'test 2', 'parents' => [ 'test 1' ] ], 
			    	[ 'name' => 'test 3', 'parents' => [ 'test 2', 'test 4' ] ], 
			    	[ 'name' => 'test 4' ],
			    ]
			);
		
			self::setTaxonomyParents();
		
			self::createProject([
				'title'   => self::PRJ_TITLE,
				'content' => self::PRJ_CONTENT,
				'summary' => self::PRJ_SUMMARY,
				'alias'   => self::PRJ_ALIAS,
				'img'     => [
					'alt'   => self::PRJ_IMG_ALT,
					'title' => self::PRJ_IMG_TITLE,
					'uri'   => self::PRJ_IMG_URI,  
				],
				'tags' => [ 'test 2', 'test 3' ],
			]);

			return [ '#title'  => 'Success!' ];
		} catch (Exception $e) {
			return [ 
				'#title' => 'Error!', 
				'#markup' => $e->getMessage()
			];
		}
	}
	
	private function createTaxonomy($vid, $name, $tags) {
		// TODO parents should be processed before their children
		if (!$vid) throw new Exception('Error: parameter $vid missing');
		if (!$name) throw new Exception('Error: parameter $name missing');
		if (empty($tags)) throw new Exception('Error: parameter $tags missing');
	
		$vocabulary = self::createVocabulary($vid, $name);
		
		foreach ($tags as $tag) {
			$term = Term::create([
				'name'   => $tag['name'],
				'vid'    => $vocabulary->id(),
				// 'parent' => self::mapTagNamesToTids($tag['parents']),
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
}