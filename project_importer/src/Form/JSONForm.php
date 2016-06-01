<?php

namespace Drupal\project_importer\Form;

use Exception;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\project_importer\Controller;

class JSONForm extends FormBase {
    
    public function getFormId() {
        return 'JSONForm';
    }
  
    public function buildForm(array $form, FormStateInterface $form_state) {

        $form['json_file'] = array(
            '#type'   => 'managed_file',
            '#title'  => $this->t('Select a JSON file which contains projects and vocabularies.'),
            '#theme'  => 'advimagearray_thumb_upload',
            '#upload_location'   => 'public://page',
            '#upload_validators' => [
		        'file_validate_extensions' => array('json'),
	        ],
            '#required' => true,
        );

        $form['submit'] = array(
            '#type'  => 'submit',
            '#value' => $this->t('Submit')
        );

        return $form;
    }
    
    public function validateForm(array &$form, FormStateInterface $form_state) {
        // @todo: Implement validateForm() method.
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        $file = file_save_upload('json_file', [], FALSE, 0);
        
        return \Drupal\project_importer\Controller\ProjectImporterController::import($file->id());
    }
}