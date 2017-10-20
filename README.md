# Simple Ontology Loader in Drupal (SOLID)

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

## Requirements:

* Drupal 8
* PHP >= 5.4

## How to install:

* Download one of our releases and extract it to a random location.
* Each release contains this readme file, a template and a folder "SOLID".
* Copy the folder "SOLID" into your Drupal 8 installation folder - into "/[drupal-root-folder]/modules". Allow the web user (e.g. www-data) read and write access to this folder.
* Log into your Drupal 8 Webpage as administrator and navigate to the menu "Extend".
* On the "Extend" page you will see a list of available and installed modules. Search for the module "SOLID" and select it.
* Click "Install" on the bottom of the page.

## How to use:

* You have to create a file with all information you want to import. Use the corresponding "template.*" file in the root folder of the release as reference.
* Log in as administrator and navigate to Config->Content->SOLID. (alternatively use the URL: "[your Drupal 8 root URL]/SOLID")
* Place the previously created file into the form.
* Check "Import Vocabularies" or "Import Nodes" and click "Import".
* You can call call SOLID/src/Script/import.php to import data from command line.

```sh
php import.php [absolute path to drupal folder] [absolute path to import file] [userid] [import vocabulary?] [import nodes?] [import classes as nodes?] [import only leaf classes as nodes?] [overwrite?]
```

## Additional features

Content imports are incrementally. If your ontology contains an entity which was imported with this module before, SOLID creates a new revision for this node and updates the fields according to owl annotation properties.

The import form contains some important checkboxes, which only affect OWL imports.
* "Import classes under 'Node' as nodes": Enables the user to handle classes as if they are individuals. The module will create a page for each class containing all properties.
* "Only import leaf classes under 'Node' as nodes": This checkbox only works in combination with the above one. Only leaf classes will be handled as individuals.

It is possible to define files, which will be attached to Nodes.
* Create a "File" Drupal field and an annotation/object property in OWL.
* Instantiate the class "http://lha.org/duo#File" to declare a file with property "http://lha.org/duo#uri".
* Annotate an OWL individual or class with above property and file individual as target.
* Make sure to place the file into the respective folder before importing the OWL file.

## Troubleshooting/Importent notes

* After the import of a vocabulary you have to assigne it to the fields, where you want to use the tags.
* If one of your declared nodes references an image or file, you have to place that image in the specified folder before importing the node.
* The user account for your web server requires read and write access to the module's folder `[Drupal root folder]/modules/SOLID`.
