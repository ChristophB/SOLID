<?php

/**
 * @file
 * Contains \Drupal\project_importer\Form\JOSNForm.
 */

namespace Drupal\project_importer\Form;

use Exception;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

use Drupal\project_importer\Importer\VocabularyImporter;
use Drupal\project_importer\Importer\NodeImporter;
use Drupal\project_importer\FileHandler\FileHandlerFactory;

class JSONForm extends FormBase {
    
    public function getFormId() {
        return 'project_importer_json_form';
    }
  
    public function buildForm(array $form, FormStateInterface $form_state) {

        $form['json_file'] = [
            '#type'   => 'managed_file',
            '#title'  => t('File:'),
            '#upload_validators' => [
		        'file_validate_extensions' => [ 'json owl' ],
	        ],
            '#required' => true,
        ];
        
        $form['overwrite'] = [
            '#type' => 'checkbox',
            '#title' => t('overwrite'),
        ];

        $form['submit'] = array(
            '#type'  => 'submit',
            '#value' => t('Submit')
        );

        return $form;
    }
    
    public function validateForm(array &$form, FormStateInterface $form_state) {
        // @todo: Implement validateForm() method.
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        $vocabularyImporter = new VocabularyImporter();
        $nodeImporter = new NodeImporter();
            
        try {
            $fileHandler = FileHandlerFactory::createFileHandler($form_state->getValue('json_file')[0]);
            $overwrite = $form_state->getValue('overwrite');
            
            $vocabularyImporter->import($fileHandler->getVocabularies(), $overwrite);
            $nodeImporter->import($fileHandler->getNodes(), $overwrite);
            
            drupal_set_message(
				sprintf(
					t('Success! %d vocabularies with %d terms and %d projects imported.'),
					$vocabularyImporter->countCreatedVocabularies(),
					$vocabularyImporter->countCreatedTags(),
					$nodeImporter->countCreatedNodes()
				)
			);
        } catch (Exception $e) {
            $nodeImporter->rollback();
            $vocabularyImporter->rollback();
			drupal_set_message(t($e->getMessage()). ' '. t('Rolling back...'), 'error');
        }
    }
	
}

?>