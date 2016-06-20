<?php

/**
 * @file
 * Contains \Drupal\project_importer\FileHandler\OWLFileHandler.
 */

namespace Drupal\project_importer\FileHandler;

use Exception;

class OWLFileHandler extends AbstractFileHandler {
	const VOCABULARY = 'http://www.lha.org/drupal_ontology#Vocabulary';
	const NODE       = 'http://www.lha.org/drupal_ontology#Page'; // Node?
	const IMG        = 'http://www.lha.org/drupal_ontology#Img';
	const ENTITY     = 'http://www.lha.org/drupal_ontology#Entity';
	const DOC        = 'http://www.lha.org/drupal_ontology#Doc';
	const TITLE      = 'http://www.lha.org/drupal_ontology#title';
	const ALIAS      = 'http://www.lha.org/drupal_ontology#alias';
	const CONTENT    = 'http://www.lha.org/drupal_ontology#content';
	const SUMMARY    = 'http://www.lha.org/drupal_ontology#summary';
	const REF_NUM    = 'http://www.lha.org/drupal_ontology#ref_num';
	const REF_TYPE   = 'http://www.lha.org/drupal_ontology#ref_type';
	const FIELD      = 'http://www.lha.org/drupal_ontology#field';
	
	private $vocabularyClassUri;
	private $nodeClassUri;
	private $aliasUri;
	private $altUri;
	private $contentUri;
	private $nameUri;
	private $summaryUri;
	private $titleUri;
	private $uriUri;
	private $hasImgUri;
	
	protected function setData() {
		$this->graph = new \EasyRdf_Graph();
		$this->graph->parse($this->fileContent);
		
		$this->setVocabularyData();
		$this->setNodeData();
	}
	
	public function getData() {
		return $this->data;
	}
	
	public function getVocabularies() {
		return $this->data['vocabularies'];
	}
	
	public function getNodes() {
		return $this->data['nodes'];
	}
	
	private function setVocabularyData() {
		foreach ($this->getVocabularyClasses() as $class) {
			$tags = [];
			
			foreach ($this->findAllSubClassesOf($class->getUri()) as $subClass) {
				$tag = [
					'name'    => $subClass->localName(),
					'parents' => $this->getParentTags($subClass)
				];
				array_push($tags, $tag);
			} 
			
			$vocabulary = [
				'vid'  => $class->localName(),
				'name' => $class->localName(),
				'tags' => $tags
			];
			
			array_push($this->data['vocabularies'], $vocabulary);
		}
	}
	
	private function getVocabularyClasses() {
		$result = [];
		
		foreach ($this->getClasses() as $class) {
			if (!$class->hasProperty('rdfs:subClassOf') 
				|| $class->getResource('rdfs:subClassOf')->getUri() != self::VOCABULARY
			) continue;
			
			array_push($result, $class);
		}
		
		return $result;
	}
	
	private function setNodeData() {
		foreach ($this->getIndividuals() as $individual) {
			if (!$this->isATransitive($individual, self::NODE))
				continue;
			
			$node = [
				'title'  => $this->getProperty($individual, self::TITLE),
				'type'   => 'article', // @todo: gather from OWL file
				'alias'  => $this->getProperty($individual, self::ALIAS),
				'fields' => $this->createNodeFields($individual)
			];
			
			
			// @todo: add image
			// $img = $this->getIndividualByUri($properties[$this->hasImgUri]);
			// $img_properties = $img ? $this->getPropertiesAsArray($img) : null;
			// if ($img_properties) {
			// 	array_push(
			// 		$node['fields'], 
			// 		[
			// 			'field_name' => 'field_image',
			// 			'value'      => [
			// 				'alt'   => $img_properties[self::ALT],
			// 				'title' => $img_properties[self::TITLE],
			// 				'uri'   => $img_properties[self::URI]
			// 			],
			// 			'entity' => 'file'
			// 		]
			// 	);
			// }
			
			array_push($this->data['nodes'], $node);
		}
	}
	
	private function createFieldTags($individual) {
		if (!$individual) throw new Exception('Error: parameter $individual missing');
		
		$fieldTags = [];
		
		foreach ($individual->allResources('rdf:type') as $tag) {
			if ($this->hasDirectSuperClass($tag, self::NODE)
				|| $tag == 'http://www.w3.org/2002/07/owl#NamedIndividual'
			)
				continue;
			
			$vocabulary = $this->getVocabularyForTag($tag);
			
			array_push(
				$fieldTags,
				[
					'vid'  => $vocabulary->localName(),
					'name' => $tag->localName()
				]
			);
		}
		
		return $fieldTags;
	}
	
	private function createNodeFields($individual) {
		if (!$individual) throw new Exception('Error: parameter $individual missing');
		
		$properties = $this->getPropertiesAsArray($individual);
		
		$fields = [
			[
				'field_name' => 'body', 
				'value'      => [ 
					'value'   => $this->parseNodeContent($individual, $properties[self::CONTENT]),
					'summary' => $properties[self::SUMMARY],
					'format'  => 'full_html'
				]
			],
			[
				'field_name' => 'field_tags',
				'value'      => $this->createFieldTags($individual),
				'references' => 'taxonomy_term'
			] 
		];
			
		foreach ($this->getAnnotationProperties() as $property) {
			if (!$individual->hasProperty($property))
				continue;
			
			if ($field = $this->createNodeField($individual, $property))
				array_push($node['fields'], $field);
		}
		
		return $fields;
	}
	
	private function createNodeField($individual, $property) {
		if (!$individual) throw new Exception('Error: parameter $individual missing');
		if (!$property) throw new Exception('Error: parameter $property missing');
		
		$field = [
			'field_name' => $property->localName()
		];
			
		if ($literals = $individual->allLiterals($property)) {
			$field['value'] = array_map(
				function ($x) { return $x->getValue(); }, 
				$literals
			);
		} elseif ($resources = $individual->allResources($property)) { // @todo use ref_num to sort values if available
			$axioms = $this->getAxiomsForIndividual($individual, $property);
		
			$field['value'] = [];
			foreach ($axioms as $axiom) {
				$target = $axiom->getResource('owl:annotatedTarget');
			
				if ($targetField = $this->getProperty($axiom, self::FIELD)) {
					array_push(
						$field['value'], 
						$this->getProperty($target, $targetField)
					);
				} else {
					array_push(
						$field['value'],
						$this->getProperty($target, self::TITLE)
					);
					$field['references'] = $this->getProperty($axiom, self::REF_TYPE); // @todo: translate to 'node', 'file' or 'tag'
				}
			}
		} else {
			return null;
		}
		
		return $field;
	}
	
	private function getProperty($entity, $uri) {
		if (!$entity) throw new Exception('Error: parameter $entity missing');
		if (!$uri) throw new Exception('Error: parameter $uri missing');
		
		return $this->getPropertiesAsArray($entity)[$uri];
	}
	
	private function parseNodeContent($node, $content) {
		if (!$content) return null;
		
		if (preg_match('/<<.*>>/', $content, $matches)) {
			foreach ($matches as $match) {
				$num = preg_replace('/<|>/', '', $match);
				$url = $this->createUrlForAxiom($node, $num);
				$content = preg_replace("/<<$num>>/", $url, $content);
			}
		}
			
		return $content;
	}
	
	private function createUrlForAxiom($individual, $num) {
		if (!$num) throw new Exception('Error: parameter $num missing');
		
		foreach ($this->getAxioms() as $axiom) {
			$properties = $this->getPropertiesAsArray($axiom);
			
			if ($axiom->hasProperty('owl:annotatedSource', $individual) 
				&& $properties[self::REF_NUM] == $num. '"^^xsd:integer'
			) {
				$target = $this->graph->resource($properties['owl:annotatedTarget']);
				$alias = $this->getProperty($target, self::ALIAS);
				
				if (!$alias) throw new Exception('Error: URLs can only reference entities with an alias.');
				
				return '<a href="'. base_path(). $alias. '">'. $target->localName(). '</a>';
			}
		}
		
		return null;
	}
	
	private function getAxiomsForIndividual($individual, $property) {
		if (!$individual) throw new Exception('Error: parameter $individual missing');
		if (!$property) throw new Exception('Error: parameter $property missing');
		
		$result = [];
		
		foreach ($this->getAxioms() as $axiom) {
			if ($axiom->hasProperty('owl:annotatedSource', $individual)
				&& $axiom->hasProperty('owl:annotatedProperty', $property)
			)
				array_push($result, $axiom);
		}
		
		return $result;
	}
	
	private function getAxioms() {
		return $this->graph->allOfType('owl:Axiom');
	}
	
	private function getVocabularyForTag($tag) {
		if (!$tag) throw new Exception('Error: parameter $tag missing');
		
		foreach ($this->getVocabularyClasses() as $vocabulary) {
			foreach ($this->findAllSubClassesOf($vocabulary->getUri()) as $subClass) {
				if ($subClass->getUri() == $tag->getUri())
					return $vocabulary;
			}
		}
		throw new Exception("Error: tag: '$tag->localName()' could not be found.");
	}
	
	private function getPropertiesAsArray($individual) {
		if (!$individual) throw new Exception('Error: parameter $individual missing');
		
		$properties = explode(' -> ', $this->graph->dumpResource($individual, 'text'));
		$array = [];
			
		for ( $i = 1; $i < sizeof($properties); $i++) {
			if ($i % 2 == 0) continue;
			$array[$properties[$i]] = trim(preg_replace('/^\s*"|"\s*$/', '', $properties[$i + 1]));
		}
		
		return $array;
	}
	
	private function getIndividuals() {
		return $this->graph->allOfType('owl:NamedIndividual');
	}
	
	private function getClasses() {
		return $this->graph->allOfType('owl:Class');
	}
	
	private function getIndividualByUri($uri) {
		if (!$uri) throw new Exception('Error: parameter $uri missing');
		
		foreach ($this->getIndividuals() as $individual) {
			if ($individual->getUri() == $uri)
				return $individual;
		}
		
		return null;
	}
	
	private function getAnnotationProperties() {
		return $this->graph->allOfType('owl:AnnotationProperty');
	}
	
	private function findAllSubClassesOf($class) {
		$result = [];
	
		foreach ($this->getClasses() as $subClass) {
			if (!$this->hasDirectSuperClass($subClass, $class))
				continue;
			array_push($result, $subClass);
			$result = array_merge($result, $this->findAllSubClassesOf($subClass->getUri()));
		}
		
		return array_unique($result);
	}
	
	private function isATransitive($class, $superClass) {
		if (!$class) throw new Exception('Error: parameter $class missing');
		if (!$superClass) throw new Exception('Error: parameter $superClass missing');
		
		foreach ($this->findAllSubClassesOf($superClass) as $curSubClass) {
			if ($class->isA($curSubClass->getUri()))
				return true;
		}
		
		return false;
	}
	
	private function hasDirectSuperClass($class, $superClass) {
		if (!$class) throw new Exception('Error: parameter $class missing');
		if (!$superClass) throw new Exception('Error: parameter $superClass missing');
		
		foreach ($this->getDirectSuperClasses($class) as $curSuperClass) {
			if ($curSuperClass == $superClass)
				return true;
		}
		return false;
	}
	
	private function getParentTags($class) {
		$result = [];
		
		foreach ($this->getDirectSuperClasses($class) as $superClass) {
			if ($this->hasDirectSuperClass($superClass, self::VOCABULARY)) {
				continue;
			} else {
				array_push($result, $superClass->localName());
			}
		}
		
		return $result;
	}
	
	private function getDirectSuperClasses($class) {
		$superClasses = [];
		
		foreach ($class->allResources('rdfs:subClassOf') as $superClass) {
			array_push($superClasses, $superClass);
		}
		
		return $superClasses;
	}
	
}
 
?>