# drupal8-project-importer

This is a Drupal 8 Module to import projects and taxonomies into Drupal, using the available API.
The module is still in development and provides no usefull functionality at it's current state.

Idea is to enable users to upload a file containing all information about projects and there relations.
The module will then import all contained information with the Drupal 8 API.

* projects become nodes of type 'article'
* the project classification becomes one (or multiple) hierachical vocabulary

Possible import formats:
* CSV
* OWL
* XML
* JSON
