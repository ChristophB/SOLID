<?php

/**
 * @file
 * Contains \Drupal\project_importer\FileHandler\OWLFileHandler.
 */

namespace Drupal\project_importer\FileHandler;

use Exception;

class OWLFileHandler extends AbstractFileHandler {
	const VOCABULARY       = 'http://www.lha.org/drupal_ontology#Vocabulary';
	const NODE             = 'http://www.lha.org/drupal_ontology#Node';
	const IMG              = 'http://www.lha.org/drupal_ontology#Img';
	const ENTITY           = 'http://www.lha.org/drupal_ontology#Entity';
	const DOC              = 'http://www.lha.org/drupal_ontology#Doc';
	const TITLE            = 'http://www.lha.org/drupal_ontology#title';
	const ALIAS            = 'http://www.lha.org/drupal_ontology#alias';
	const CONTENT          = 'http://www.lha.org/drupal_ontology#content';
	const SUMMARY          = 'http://www.lha.org/drupal_ontology#summary';
	const REF_NUM          = 'http://www.lha.org/drupal_ontology#ref_num';
	const REF_TYPE         = 'http://www.lha.org/drupal_ontology#ref_type';
	const NODE_REF         = 'http://www.lha.org/drupal_ontology#node_ref';
	const FILE_REF         = 'http://www.lha.org/drupal_ontology#file_ref';
	const IMAGE_REF        = 'http://www.lha.org/drupal_ontology#image_ref';
	const FIELD            = 'http://www.lha.org/drupal_ontology#field';
	const URI              = 'http://www.lha.org/drupal_ontology#uri';
	const ALT              = 'http://www.lha.org/drupal_ontology#alt';
	const NAMED_INDIVIDUAL = 'http://www.w3.org/2002/07/owl#NamedIndividual';
	
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
				'type'   => $this->getBundle($individual),
				'alias'  => $this->getProperty($individual, self::ALIAS),
				'fields' => $this->createNodeFields($individual)
			];
			
			array_push($this->data['nodes'], $node);
		}
	}
	
	private function getBundle($node) {
		if (!$node) throw new Exception('Error: parameter $node missing');
	
		return strtolower(array_values(array_filter(
			$node->typesAsResources(),
			function($x) { return $this->hasDirectSuperClass($x, self::NODE); }
		))[0]->localName());
	}
	
	private function createFieldTags($individual) {
		if (!$individual) throw new Exception('Error: parameter $individual missing');
		
		$fieldTags = [];
		
		foreach ($individual->allResources('rdf:type') as $tag) {
			if ($this->hasDirectSuperClass($tag, self::NODE)
				|| $tag == self::NAMED_INDIVIDUAL
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
				array_push($fields, $field);
		}
		
		return $fields;
	}
	
	private function createNodeField($individual, $property) {
		if (!$individual) throw new Exception('Error: parameter $individual missing');
		if (!$property) throw new Exception('Error: parameter $property missing');
			
		if ($literals = $individual->allLiterals($property)) {
			$field = [
				'value' => array_map(
					function ($x) { return $x->getValue(); }, 
					$literals
				),
				'field_name' => $property->localName()
			];
		} elseif ($individual->allResources($property)) { 
			$field = $this->getResourceValuesForNodeField($individual, $property);
		} else {
			return null;
		}
		
		return $field;
	}
	
	private function getResourceValuesForNodeField($individual, $property) {
		if (!$individual) throw new Exception('Error: parameter $individual missing');
		if (!$property) throw new Exception('Error: parameter $property missing');
		
		$resources = $individual->allResources($property);
		if (!$resources || empty($resources)) return null;
		
		$axioms = $this->getAxiomsForIndividual($individual, $property);
		
		$field = [
			'value' => [],
			'field_name' => $property->localName()
		];
		foreach ($axioms as $axiom) {
			$target = $axiom->getResource('owl:annotatedTarget');
			$axiomProperties  = $this->getPropertiesAsArray($axiom);
			$targetProperties = $this->getPropertiesAsArray($target);
			$refType = array_key_exists(self::REF_TYPE, $axiomProperties) ?
				$axiomProperties[self::REF_TYPE] : null;
				
			if ($targetField = $axiomProperties[self::FIELD]) {
				array_push(
					$field['value'], 
					$targetProperties[$targetField]
				);
			} else {
				$value;
			
				switch($refType) {
					case self::FILE_REF:
						$value = [
							'uri'   => $targetProperties[self::URI],
							'title' => $targetProperties[self::TITLE]
						];
						$field['entity'] = 'file';
						break;
					case self::IMAGE_REF:
						$value = [
							'alt'   => $targetProperties[self::ALT],
							'title' => $targetProperties[self::TITLE],
							'uri'   => $targetProperties[self::URI]
						];
						$field['entity'] = 'file';
						break;
					case self::NODE_REF: 
						$value = $targetProperties[self::TITLE];
						$field['references'] = preg_replace(
							'/_ref/', '',
							$this->graph->resource($refType)->localName()
						);
						break;
					default:
						throw new Exception(
							'Could not determine target fields, because no '
							. 'ref_type or ref_type is not supported was given.'
						);
				}
				array_push($field['value'], $value);
			}
		}
		
		return $field;
	}
	
	private function getProperty($entity, $uri) {
		if (!$entity) throw new Exception('Error: parameter $entity missing');
		if (!$uri) throw new Exception('Error: parameter $uri missing');
		
		$properties = $this->getPropertiesAsArray($entity);
		if (!array_key_exists($uri, $properties))
			return null;
		
		return $properties[$uri];
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
				$uri = $this->getProperty($target, self::URI);
				
				if (!$uri) {
					$alias = $this->getProperty($target, self::ALIAS);
				
					if (!$alias) throw new Exception('Error: URLs can only reference entities with uri or alias. ('. $target->localName(). ')');
					$uri = base_path(). $alias;
				}
				
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
				$result[preg_replace('/"\^\^xsd:integer/', '', $this->getProperty($axiom, self::REF_NUM))] = $axiom;
		}
		ksort($result);
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
	
	private function getAnnotationProperties() {
		return $this->graph->allOfType('owl:AnnotationProperty');
	}
	
	private function findAllSubClassesOf($class) { // recursive
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
		
		if (in_array($superClass, $class->allResources('rdfs:subClassOf')))
			return true;
		
		return false;
	}
	
	private function getParentTags($class) {
		if (!$class) throw new Exception('Error: parameter $class missing');
		
		return array_filter(
			$class->allResources('rdfs:subClassOf'),
			function($x) { return $this->hasDirectSuperClass($x, self::VOCABULARY); }
		);
	}
	
}
 
?>