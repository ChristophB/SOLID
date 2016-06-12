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

// use Drupal\project_importer\Importer\ProjectImporter;
use Drupal\project_importer\Importer\VocabularyImporter;
use Drupal\project_importer\Importer\NodeImporter;

class JSONForm extends FormBase {
    
    public function getFormId() {
        return 'project_importer_json_form';
    }
  
    public function buildForm(array $form, FormStateInterface $form_state) {

        $form['json_file'] = [
            '#type'   => 'managed_file',
            '#title'  => t('JSON file.'),
            '#upload_location'   => 'public://page',
            '#upload_validators' => [
		        'file_validate_extensions' => [ 'json' ],
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
            $data = $this->handleJsonFile($form_state->getValue('json_file')[0]);
            $overwrite = $form_state->getValue('overwrite');
            
            $vocabularyImporter->import($data['vocabularies'], $overwrite);
            $nodeImporter->import($data['nodes'], $overwrite);
            
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
    
    private function handleJsonFile($fid) {
		$json_file = File::load($fid);
		$data = file_get_contents(drupal_realpath($json_file->getFileUri()));
		
		$data = json_decode($data, TRUE);
		
		if (json_last_error() != 0) throw new Exception('Error: Could not decode the json file.');
		
		return $data;
	}
}

?>