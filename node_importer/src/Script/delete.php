<?php

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;

if (sizeof($argv) < 3)
    die('Usage: delete.php [drupal path] [content type] [userId?]'. PHP_EOL);

$drupalPath  = $argv[1];
$contentType = $argv[2];
$userId      = $argv[3];

if (is_empty($drupalPath)) die('Error: script parameter "drupalPath" missing.'. PHP_EOL);
if (is_empty($contentType)) die('Error: script parameter "contentType" missing.'. PHP_EOL);


$autoloader = require_once "$drupalPath/autoload.php";
$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
$kernel->boot();
$kernel->prepareLegacyRequest($request);

echo "Deleting all nodes of type '$contentType'...", PHP_EOL;
$result = \Drupal::entityQuery('node')
    ->addMetaData('account', user_load($userId))
    ->condition('type', $contentType)
    ->execute();

echo 'Found '. sizeof($result). ' nodes.', PHP_EOL;

foreach ($result as $id) {
    Node::load($id)->delete();
}

?>
