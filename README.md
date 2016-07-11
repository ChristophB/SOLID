# drupal8-node-importer

## General information:

This is a Drupal 8 Module to import nodes and taxonomies into Drupal, using the available API.
The module is still in development but is already capable of importing a JSON or OWL file.

Idea is to enable users to upload a file containing all information about nodes and their relations.
The module will then import all contained information with the Drupal 8 API.

* nodes can be specified as 'articles' or any other custom node type
* the node classification becomes one (or multiple) hierachical vocabulary

Supported import formats:
* JSON
* OWL

## How to install:

* Download one of our releases and extract it to a random location.
* Each release contains this readme file, a template and a folder "node_importer".
* Copy the folder "node_importer" into your Drupal 8 installation folder - into "/[drupal-root-folder]/modules".
* Log into your Drupal 8 Webpage as administrator and navigate to the menu "Extend".
* On the "Extend" page you will see a list of available and installed modules. Search for the module "Node Importer" and select it.
* Click "Install" on the bottom of the page.

## How to use:

* You have to create a file with all information you want to import. Use the corresponding "template.*" file in the root folder of the release as reference.
* Log in as administrator and navigate to Config->Content->Node Importer. (alternatively use the URL: "[your Drupal 8 root URL]/node_importer")
* Place the previously created file into the form and click "Import".
* There will be a message indicating the success/failure of the import process.
* The module automatically undos all changes if an error occured.


## Troubleshooting/Importent notes

* After the import of a vocabulary you have to assigne it to the fields, where you want to use the tags.
* If one of your declared nodes references an image or file, you have to place that image in the specified folder before importing the node.
