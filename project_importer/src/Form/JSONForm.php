<?php

/**
 * @file
 * Contains \Drupal\project_importer\Form\JOSNForm.
 */

namespace Drupal\project_importer\Form;

use Exception;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\project_importer\Importer\ProjectImporter;

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
        return (new ProjectImporter())->import(
            $form_state->getValue('json_file')[0], 
            $form_state->getValue('overwrite')
        );
    }
}

?>