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
		$this->summaryUri = $this->getAnnotationPropertyUri('summary');
		$this->titleUri = $this->getAnnotationPropertyUri('title');
		$this->uriUri = $this->getAnnotationPropertyUri('uri');
	}
	
	private function setVocabularyData() {
		foreach ($this->getClasses() as $class) {
			if (!$class->hasProperty('rdfs:subClassOf') 
				|| $class->getResource('rdfs:subClassOf')->localName() != 'Vocabulary'
			) continue;
			
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
	
	private function setNodeData() {
		foreach ($this->getIndividuals() as $individual) {
			if (!$individual->isA($this->nodeClassUri))
				continue;
				
			$properties = $this->getIndividualPropertiesAsArray($individual);
			
			$node = [
				'title'         => $properties[$this->titleUri],
				'content'       => $properties[$this->contentUri],
				'summary'       => $properties[$this->summaryUri],
				'alias'         => $properties[$this->aliasUri],
				'img'           => [],
				'tags'          => [],
				'custom_fields' => []
			];
			
			array_push($this->data['nodes'], $node);
		}
	}
	
	private function getIndividualPropertiesAsArray($individual) {
		if (!$individual) throw new Exception('Error: parameter $individual missing');
		
		$properties = explode(' -> ', $this->graph->dumpResource($individual, 'text'));
		$array = [];
			
		for ( $i = 1; $i < sizeof($properties); $i++) {
			if ($i % 2 == 0) continue;
			$array[$properties[$i]] = preg_replace('/(^\s*")|("\s*$)/', '', $properties[$i + 1]);
			
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
		
		foreach ($this->getClasses() as $class) {
			if ($class->localName() == $name)
				return $class->getUri();
		}
	}
	
	private function getAnnotationPropertyUri($name) {
		if (!$name) throw new Exception('Error: parameter $name missing');
		
		foreach ($this->getAnnotationProperties() as $ap) {
			if ($ap->localName() == $name)
				return $ap->getUri();
		}
	}
}
 
?>