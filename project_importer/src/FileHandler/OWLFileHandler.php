<?php

/**
 * @file
 * Contains \Drupal\project_importer\FileHandler\OWLFileHandler.
 */

namespace Drupal\project_importer\FileHandler;

use Exception;

class OWLFileHandler extends AbstractFileHandler {
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
	
	public function getData() {
		return $this->data;
	}
	
	public function getVocabularies() {
		return $this->data['vocabularies'];
	}
	
	public function getNodes() {
		return $this->data['nodes'];
	}
	
	protected function setData() {
		$this->graph = new \EasyRdf_Graph();
		$this->graph->parse($this->fileContent);
		
		$this->setUris();
		
		$this->setVocabularyData();
		$this->setNodeData();
	}
	
	private function setUris() {
		$this->vocabularyClassUri = $this->getClassUri('Vocabulary');
		$this->nodeClassUri = $this->getClassUri('Node');
		$this->aliasUri = $this->getAnnotationPropertyUri('alias');
		$this->altUri = $this->getAnnotationPropertyUri('alt');
		$this->contentUri = $this->getAnnotationPropertyUri('content');
		$this->nameUri = $this->getAnnotationPropertyUri('name');
		$this->summaryUri = $this->getAnnotationPropertyUri('summary'); // better as one array to store additional data?
		$this->titleUri = $this->getAnnotationPropertyUri('title');
		$this->uriUri = $this->getAnnotationPropertyUri('uri');
		$this->hasImgUri = $this->getObjectPropertyUri('has_img');
	}
	
	private function setVocabularyData() {
		foreach ($this->getVocabularyClasses() as $class) {
			$tags = [];
			
			foreach ($this->findAllSubClassesOf($class) as $subClass) {
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
				|| $class->getResource('rdfs:subClassOf')->localName() != 'Vocabulary'
			) continue;
			
			array_push($result, $class);
		}
		
		return $result;
	}
	
	private function setNodeData() {
		foreach ($this->getIndividuals() as $individual) {
			if (!$individual->isA($this->nodeClassUri))
				continue;
				
			$properties = $this->getIndividualPropertiesAsArray($individual);
			$img = $this->getIndividualByUri($properties[$this->hasImgUri]);
			$img_properties = $img ? $this->getIndividualPropertiesAsArray($img) : null;
			
			$fieldTags = [];
			foreach ($individual->allResources('rdf:type') as $class) {
				if ($class->getUri() == $this->nodeClassUri
					|| $class->localName() == 'NamedIndividual')
					continue;
				$vocabulary = $this->getVocabularyForTag($class);
				
				array_push(
					$fieldTags,
					[
						'vid'  => $vocabulary->localName(),
						'name' => $class->localName()
					]
				);
			}
			
			
			$node = [
				'title'  => $properties[$this->titleUri],
				'type'   => 'article', // @todo: gather from OWL file
				'alias'  => $properties[$this->aliasUri],
				'fields' => [
					[
						'field_name' => 'body', 
						'value'      => [ 
							'value'   => $properties[$this->contentUri],
							'summary' => $properties[$this->summaryUri]
						]
					],
					[
						'field_name' => 'field_tags',
						'value'      => $fieldTags,
						'references' => 'taxonomy_term'
					] 
					// @toto: handle all fields
				]
			];
			
			
			if ($img_properties) {
				array_push(
					$node['fields'], 
					[
						'field_name' => 'field_image',
						'value'      => [
							'alt'   => $img_properties[$this->altUri],
							'title' => $img_properties[$this->titleUri],
							'uri'   => $img_properties[$this->uriUri]
						],
						'entity' => 'file'
					]
				);
			}
			
			array_push($this->data['nodes'], $node);
		}
	}
	
	private function getVocabularyForTag($tag) {
		if (!$tag) throw new Exception('Error: parameter $tag missing');
		
		foreach ($this->getVocabularyClasses() as $vocabulary) {
			foreach ($this->findAllSubClassesOf($vocabulary) as $subClass) {
				if ($subClass->getUri() == $tag->getUri())
					return $vocabulary;
			}
		}
		throw new Exception("Error: tag: '$tag->localName()' could not be found.");
	}
	
	private function getIndividualPropertiesAsArray($individual) {
		if (!$individual) throw new Exception('Error: parameter $individual missing');
		
		$properties = explode(' -> ', $this->graph->dumpResource($individual, 'text'));
		$array = [];
			
		for ( $i = 1; $i < sizeof($properties); $i++) {
			if ($i % 2 == 0) continue;
			$array[$properties[$i]] = trim(preg_replace('/(^\s*")|("\s*$)/', '', $properties[$i + 1]));
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
	
	private function getObjectProperties() {
		return $this->graph->allOfType('owl:ObjectProperty');
	}
	
	private function findAllSubClassesOf($class) {
		$result = [];
	
		foreach ($this->getClasses() as $subClass) {
			if (!$this->hasSuperClass($subClass, $class))
				continue;
			
			array_push($result, $subClass);
			$result = array_merge($result, $this->findAllSubClassesOf($subClass));
		}
		
		return array_unique($result);
	}
	
	private function hasSuperClass($class, $superClass) {
		if (!$class) throw new Exception('Error: parameter $class missing');
		if (!$superClass) throw new Exception('Error: parameter $superClass missing');
		
		foreach ($class->allResources('rdfs:subClassOf') as $curSuperClass) {
			if ($curSuperClass->getUri() == $superClass->getUri())
				return true;
		}
		return false;
	}
	
	private function getParentTags($class) {
		$result = [];
		
		foreach ($this->getDirectSuperClasses($class) as $superClass) {
			if ($this->hasSuperClass($superClass, $this->graph->resource($this->vocabularyClassUri))) {
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
	
	private function getClassUri($name) {
		if (!$name) throw new Exception('Error: parameter $name missing');
		
		return $this->getResourceUri($name, $this->getClasses());
	}
	
	private function getAnnotationPropertyUri($name) {
		if (!$name) throw new Exception('Error: parameter $name missing');
		
		return $this->getResourceUri($name, $this->getAnnotationProperties());
	}
	
	private function getObjectPropertyUri($name) {
		if (!$name) throw new Exception('Error: parameter $name missing');
		
		return $this->getResourceUri($name, $this->getObjectProperties());
	}
	
	private function getResourceUri($name, $resources) {
		if (!$name) throw new Exception('Error: parameter $name missing');
		
		foreach ($resources as $resource) {
			if ($resource->localName() == $name)
				return $resource->getUri();
		}
	}
}
 
?>