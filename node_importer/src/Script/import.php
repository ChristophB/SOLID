<?php

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use \Exception;

use Drupal\node_importer\Importer\VocabularyImporter;
use Drupal\node_importer\Importer\NodeImporter;
use Drupal\node_importer\FileHandler\FileHandlerFactory;

if (sizeof($argv) < 2)
    die("Usage: import.php [drupal path] [file path] [user id] [import vocabularies?] [import nodes?] [classes as nodes?] [only leaf classes as nodes?] [overwrite?]\n");

$drupalPath             = $argv[1];
$filePath               = $argv[2];
$userId                 = $argv[3];
$importVocabularies     = $argv[4] ? true : false;
$importNodes            = $argv[5] ? true : false;
$classesAsNodes         = $argv[6] ? true : false;
$onlyLeafClassesAsNodes = $argv[7] ? true : false;
$overwrite              = $argv[8] ? true : false;

if (!$drupalPath) die("Error: script parameter 'drupalPath' missing.\n");
if (!$filePath) die("Error: script parameter 'filePath' missing.\n");

$logFile = 'modules/node_importer/node_importer.log';
if (file_exists($logFile)) unlink($logFile);
fclose(STDOUT);
$STDOUT = fopen($logFile, 'wb');

$autoloader = require_once $drupalPath. '/autoload.php';

$request = Request::createFromGlobals();

$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
$kernel->boot();
$kernel->prepareLegacyRequest($request);

$msg = 'Start: '. memory_get_usage(false);
doLog($msg);

$vocabularyImporter = new VocabularyImporter($overwrite, $userId);
$nodeImporter       = new NodeImporter($overwrite, $userId);
        
try {
    $fileHandler = FileHandlerFactory::createFileHandler([
        'path'                   => $filePath,
        'vocabularyImporter'     => $vocabularyImporter,
        'nodeImporter'           => $nodeImporter,
        'classesAsNodes'         => $classesAsNodes,
        'onlyLeafClassesAsNodes' => $onlyLeafClassesAsNodes,
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
    $nodeImporter->rollback();
    $vocabularyImporter->rollback();
       
    logMemoryUsage();
    
    $msg
    	= t($e->getMessage())
	    . ' In '. $e->getFile(). ' (line:'. $e->getLine(). ')'
	    . ' '. t('Rolling back...');
	doLog($msg);
}

unlink($filePath);
fclose($STDOUT);
unlink($logFile);


function doLog($msg) {
	\Drupal::logger('node_importer')->notice($msg);
	print date('H:m:s', time()). "> $msg\n";
}

function logMemoryUsage() {
	doLog(
    	'End: '. memory_get_usage(false). "\n"
    	. 'Peak: '. memory_get_peak_usage(false)
    );
}

?>