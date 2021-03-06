<?php

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
# use \Exception;

use Drupal\SOLID\Importer\VocabularyImporter;
use Drupal\SOLID\Importer\NodeImporter;
use Drupal\SOLID\FileHandler\FileHandlerFactory;

if (sizeof($argv) < 2) {
    doLog('Usage: import.php [drupal path] [file path] [user id] [import vocabularies?] '
        . '[import nodes?] [classes as nodes?] [only leaf classes as nodes?] [overwrite?]'. PHP_EOL
    );
    die;
}

$drupalPath             = $argv[1];
$filePath               = $argv[2];
$userId                 = intval($argv[3]);
$importVocabularies     = $argv[4] ? true : false;
$importNodes            = $argv[5] ? true : false;
$classesAsNodes         = $argv[6] ? true : false;
$onlyLeafClassesAsNodes = $argv[7] ? true : false;
$overwrite              = $argv[8] ? true : false;

if (empty($drupalPath)) {
    die('Error: script parameter "drupalPath" missing.'. PHP_EOL);
}

$autoloader = require_once "$drupalPath/autoload.php";
$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
$kernel->boot();
$kernel->prepareLegacyRequest($request);

if (empty($filePath)) {
    doLog('Error: script parameter "filePath" missing.'. PHP_EOL);
    die;
}

$logFile = "$drupalPath/modules/SOLID/SOLID.log";
if (file_exists($logFile)) unlink($logFile);
fclose(STDOUT);
$STDOUT = fopen($logFile, 'wb');

if (!file_exists($logFile) || !is_writable($logFile)) {
  doLog('Could not open log file. Please check permissions.');
  die;
}

doLog('Start: '. memory_get_usage(false));

$vocabularyImporter = new VocabularyImporter($overwrite, $userId);
$nodeImporter       = new NodeImporter($overwrite, $userId);
        
try {
    $fileHandler = FileHandlerFactory::createFileHandler([
        'path'                   => $filePath,
        'vocabularyImporter'     => $vocabularyImporter,
        'nodeImporter'           => $nodeImporter,
        'classesAsNodes'         => $classesAsNodes,
        'onlyLeafClassesAsNodes' => $onlyLeafClassesAsNodes
    ]);
    
    if ($importVocabularies)
        $fileHandler->setVocabularyData();
    if ($importNodes)
        $fileHandler->setNodeData();
    
    logMemoryUsage();
    
    $msg = sprintf(
		t('Success! %d vocabularies with %d terms and %d nodes imported.'),
		$vocabularyImporter->countCreatedVocabularies(),
		$vocabularyImporter->countCreatedTags(),
		$nodeImporter->countCreatedNodes()
	);
    doLog($msg);
} catch (Exception $e) {
    # $nodeImporter->rollback();
    # $vocabularyImporter->rollback();
       
    logMemoryUsage();
    
    $msg
    	= t($e->getMessage())
	    . " In {$e->getFile()} (line:{$e->getLine()}) "
	    # . t('Rolling back...')
        ;
	doLog($msg);
}

# unlink($filePath);
fclose($STDOUT);
# unlink($logFile);


function doLog($msg) {
	if (class_exists('Drupal'))
	\Drupal::logger('SOLID')->notice($msg);
	echo date('H:i:s', time()). "> $msg", PHP_EOL;
}

function logMemoryUsage() {
	doLog(
    	'End: '. memory_get_usage(false)
    	. ', Peak: '. memory_get_peak_usage(false)
    );
}

?>
