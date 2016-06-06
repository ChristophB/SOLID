# drupal8-project-importer

## General information:

This is a Drupal 8 Module to import projects and taxonomies into Drupal, using the available API.
The module is still in development but is already capable of importing a JSON file.

Idea is to enable users to upload a file containing all information about projects and their relations.
The module will then import all contained information with the Drupal 8 API.

* projects become nodes of type 'article'
* the project classification becomes one (or multiple) hierachical vocabulary

Supported import formats:
* JSON

## How to install:

* Download one of our releases and extract it to a random location.
* Each release contains this readme file, a templates and a folder "project_importer".
* Copy the folder "project_importer" into your Drupal 8 installation folder - into "/[drupal-root-folder]/modules".
* Log into your Drupal 8 Webpage as administrator and navigate to the menu "Extend".
* On the extend page you will see a list of available and installed modules. Search for the module "Project Importer" and select it.
* Click "Install" on the botom of the page.

## How to use:

* You have to create a file with all information you want to import. Use the corresponding "template.*" file in the root folder of the release as reference.
* Log in as administrator and navigate to Config->Content->Project Importer. (alternatively use the URL: "[your Drupal 8 root URL]/project_importer")
* Place the previously created file into the form and click "Import".
* There will be a message indicating the success/failure of the import process.
* The module automatically undos all changes if an error occured.
* If one of your declared nodes references an image, you have to place that image in the specified folder first.