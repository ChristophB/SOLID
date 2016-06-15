<?php

/**
 * @file
 * Contains \Drupal\project_importer\FileHandler\OWLFileHandler.
 */

namespace Drupal\project_importer\FileHandler;

class OWLFileHandler extends AbstractFileHandler {
	
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
		
		foreach ($this->getClasses() as $class) {
			if (!$class->hasProperty('rdfs:subClassOf') || $class->getResource('rdfs:subClassOf')->localName() != 'Vocabulary')
				continue;
			
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
	
	private function getClasses() {
		return $this->graph->allOfType('owl:Class');
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
			if ($this->hasSuperClass($superClass, $this->graph->resource($this->getVocabularieClassUri()))) {
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
	
	private function getVocabularieClassUri() {
		foreach ($this->getClasses() as $class) {
			if ($class->localName() == 'Vocabulary')
				return $class->getUri();
		}
	}
	
}
 
?>