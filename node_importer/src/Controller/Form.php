<?php

/**
 * @file
 * Contains \Drupal\node_importer\Controller\Form.
 */

namespace Drupal\node_importer\Controller;

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
            '#title' => t('Only import leaf classes under "Node" as nodes'),
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
        $fid                    = $form_state->getValue('file')[0];
        $importVocabularies     = $form_state->getValue('import_vocabularies');
        $importNodes            = $form_state->getValue('import_nodes');
        $classesAsNodes         = $form_state->getValue('import_class_nodes');
        $onlyLeafClassesAsNodes = $form_state->getValue('import_only_leaf_class_nodes');
        $overwrite              = $form_state->getValue('overwrite');
        $userId                 = \Drupal::currentUser()->id();
        
        // if log-file exists dont allow another import process
        
        
        $file = File::load($fid);
        $uri = $file->getFileUri();
        $filePath = \Drupal::service('file_system')->realpath($uri);
        $drupalPath = getcwd();
        $newFile = $drupalPath. '/sites/default/files/'. $file->getFilename();
        copy($filePath, $newFile);
        
        $cmd 
            = "php -q modules/node_importer/src/Script/import.php $drupalPath "
            . "$newFile $userId $importVocabularies $importNodes $classesAsNodes "
            . "$onlyLeafClassesAsNodes $overwrite";
        
        $this->execInBackground($cmd);
        
        
        drupal_set_message(
			'Import started! Have a look at /admin/reports/dblog to see the progress.'
		);
    }
	
	private function execInBackground($cmd) { 
        if (substr(php_uname(), 0, 7) == "Windows"){ 
            pclose(popen("start /B ". $cmd, "r"));  
        } 
        else { 
            exec($cmd. " > /dev/null &");   
        }
    }
    
}

?>