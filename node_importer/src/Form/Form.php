<?php

/**
 * @file
 * Contains \Drupal\node_importer\Form\Form.
 */

namespace Drupal\node_importer\Form;

use Exception;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

use Drupal\node_importer\Importer\VocabularyImporter;
use Drupal\node_importer\Importer\NodeImporter;
use Drupal\node_importer\FileHandler\FileHandlerFactory;

/**
 * Main Class which is instantiated by callung "/node_importer"
 * 
 * @author Christoph Beger
 */
class Form extends FormBase {
    
    public function getFormId() {
        return 'node_importer_form';
    }
  
    public function buildForm(array $form, FormStateInterface $form_state) {

        $form['file'] = [
            '#type'   => 'managed_file',
            '#title'  => t('File:'),
            '#upload_validators' => [
		        'file_validate_extensions' => [ 'json owl' ],
	        ],
            '#required' => true,
        ];
        
        $form['import_vocabularies'] = [
            '#type'  => 'checkbox',
            '#title' => t('Import Vocabularies'),
        ];
        
        $form['import_nodes'] = [
            '#type'  => 'checkbox',
            '#title' => t('Import Nodes'),
        ];
        
        $form['import_class_nodes'] = [
            '#type'  => 'checkbox',
            '#title' => t('Import classes under "Node" as nodes'),
        ];
        
        $form['import_only_leaf_class_nodes'] = [
            '#type'  => 'checkbox',
            '#title' => t('Only import leaf classes unter "Node" as nodes'),
        ];
        
        $form['overwrite'] = [
            '#type'  => 'checkbox',
            '#title' => t('Overwrite'),
        ];

        $form['submit'] = [
            '#type'  => 'submit',
            '#value' => t('Submit')
        ];

        return $form;
    }
    
    public function validateForm(array &$form, FormStateInterface $form_state) {
        // @todo Implement validateForm() method.
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        $overwrite = $form_state->getValue('overwrite');
            
        $vocabularyImporter = new VocabularyImporter($overwrite);
        $nodeImporter       = new NodeImporter($overwrite);
        
        try {
            $fileHandler = FileHandlerFactory::createFileHandler([
                'fid'                => $form_state->getValue('file')[0],
                'vocabularyImporter' => $vocabularyImporter,
                'nodeImporter'       => $nodeImporter,
                'classesAsNodes'     => $form_state->getValue('import_class_nodes'),
                'onlyLeafClassesAsNodes' => $form_state->getValue('import_only_leaf_class_nodes'),
            ]);
            
            if ($form_state->getValue('import_vocabularies'))
                $fileHandler->setVocabularyData();
            if ($form_state->getValue('import_nodes'))
                $fileHandler->setNodeData();
        
            drupal_set_message(
				sprintf(
					t('Success! %d vocabularies with %d terms and %d nodes imported.'),
					$vocabularyImporter->countCreatedVocabularies(),
					$vocabularyImporter->countCreatedTags(),
					$nodeImporter->countCreatedNodes()
				)
			);
        } catch (Exception $e) {
            $nodeImporter->rollback();
            $vocabularyImporter->rollback();
			drupal_set_message(
			    t($e->getMessage())
			    . ' In '. $e->getFile(). ' (line:'. $e->getLine(). ')'
			    . ' '. t('Rolling back...'),
			    'error'
			);
        }
    }
	
}

?>