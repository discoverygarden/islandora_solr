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

Install as
[usual](https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules).

## Configuration

This module has extensive configuration optons.

![Configuration](http://i.imgur.com/qhELL78.png)

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

## Documentation

Further documentation for this module is available at
[our wiki](https://wiki.duraspace.org/display/ISLANDORA/Islandora+Solr+Search).

## FAQ

Q: Why can't I connect to Solr via https?

A: The Apache Solr PHP Client that we use does not support https connections to
Solr. [ISLANDORA-646](https://jira.duraspace.org/browse/ISLANDORA-646) seeks
to remedy this.

## Troubleshooting/Issues

Having problems or solved one? Create an issue, check out the Islandora Google
groups.

* [Users](https://groups.google.com/forum/?hl=en&fromgroups#!forum/islandora)
* [Devs](https://groups.google.com/forum/?hl=en&fromgroups#!forum/islandora-dev)

or contact [discoverygarden](http://support.discoverygarden.ca).

## Maintainers/Sponsors

Current maintainers:

* [discoverygarden](http://www.discoverygarden.ca)

## Development

If you would like to contribute to this module, please check out the helpful
[Documentation](https://github.com/Islandora/islandora/wiki#wiki-documentation-for-developers),
[Developers](http://islandora.ca/developers) section on Islandora.ca and create
an issue, pull request and or contact
[discoverygarden](http://support.discoverygarden.ca).

## License

[GPLv3](http://www.gnu.org/licenses/gpl-3.0.txt)
