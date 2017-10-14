# Islandora Solr Search

## Introduction

Islandora Solr provides a highly configurable interface to perform Solr queries
and display Solr data in different ways.

## Requirements

This module requires the following modules/libraries:

* [Islandora](https://github.com/islandora/islandora)
* [Tuque](https://github.com/islandora/tuque)
* [Apache Solr](https://lucene.apache.org/solr/) - 4.2 or higher.

## Installation

Install as usual, see
[this](https://drupal.org/documentation/install/modules-themes/modules-7) for
further information.

## Configuration

Set the 'Solr URL' and select a 'Request handler' in
Administration » Islandora » Solr Index
(admin/islandora/search/islandora_solr).

![Configuration](http://i.imgur.com/qhELL78.png)

Islandora Solr Search offers many more configuration options in
Administration » Islandora » Solr Index » Solr settings
(admin/islandora/search/islandora_solr/settings).

The module has many blocks that provide enriching functionality:

* Simple search
* Advanced search
* Facets
* Current query
* Search navigation
* Explore
* Display switch
* Results sorting

Islandora Solr Search also implements the Islandora Basic Collection solution
pack's query backend to drive the collection display using Solr instead of
SPARQL/Fedora. This functionality can be applied on the collection solution
pack's configuration page
(admin/islandora/solution_pack_config/basic_collection), and that same page
provides settings for sorting the Solr collection view globally and
per-collection. The query backend relies on the relationship fields in the
"Required Solr Fields" section of the Solr settings; the fields in that section
should be confirmed before using Solr to drive the display.

### Breadcrumbs

To enable breadcrumbs, browse to Administration » Islandora » Configuration and choose **Islandora Solr** under **Breadcrumb generation**.
![Configuration](https://cloud.githubusercontent.com/assets/2857697/19577960/1c70a1c8-96df-11e6-8a7b-92fa16c30137.jpg)

## Documentation

Further documentation for this module is available at
[our wiki](https://wiki.duraspace.org/display/ISLANDORA/Islandora+Solr+Search).

## Troubleshooting/Issues

 Having problems or solved a problem? Check out the Islandora google groups for a solution.

 * [Islandora Group](https://groups.google.com/forum/?hl=en&fromgroups#!forum/islandora)
 * [Islandora Dev Group](https://groups.google.com/forum/?hl=en&fromgroups#!forum/islandora-dev)

## FAQ

Q: Why can't I connect to Solr via https?

A: The Apache Solr PHP Client that we use does not support https connections to Solr. [ISLANDORA-646](https://jira.duraspace.org/browse/ISLANDORA-646) seeks to remedy this.

## Development

In addition, we have helpful [Documentation for Developers](https://github.com/Islandora/islandora/wiki#wiki-documentation-for-developers) info, as well as our [Developers](http://islandora.ca/developers) section on the [Islandora.ca](http://islandora.ca) site.

## License

[GPLv3](http://www.gnu.org/licenses/gpl-3.0.txt)
