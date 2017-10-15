<?php

/**
 * @file
 * Miscellaneous helper functions.
 */

use Drupal\islandora_solr\IslandoraSolrQueryProcessor;

/**
 * Generates the links for the Islandora explore block.
 *
 * @return output
 *   A rendered list containing explore links to islandora solr search and
 * object counts.
 */
function islandora_solr_explore_generate_links() {
  $output = '';
  $links = array();
  // @FIXME
// Could not extract the default value because it is either indeterminate, or
// not scalar. You'll need to provide a default value in
// config/install/islandora_solr.settings.yml and config/schema/islandora_solr.schema.yml.
$expore_config = \Drupal::config('islandora_solr.settings')->get('islandora_solr_explore_config');
  if (!empty($expore_config)) {
    $qp = new IslandoraSolrQueryProcessor();
    $qp->buildQuery('*:*');
    $qp->solrParams['fl'] = '*';
    $qp->solrParams['facet.limit'] = 100000;
    $qp->solrParams['facet.mincount'] = \Drupal::config('islandora_solr.settings')->get('islandora_solr_facet_min_limit');
    $qp->solrParams['facet.field'] = array();
    $qp->solrParams['fl'] = 'PID';
    foreach ($expore_config as $index => $option) {
      $qp->solrParams['facet.query'][] = $option['filter'];
    }
    $qp->executeQuery(FALSE);
    foreach ($expore_config as $index => $option) {
      $url = htmlspecialchars(urlencode($option['filter']));
      // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $links[] = l(
//         htmlentities($option['label']),
//         "islandora/search/$url",
//         array()
//       ) . '&nbsp;<span>(' . htmlentities($qp->islandoraSolrResult['facet_counts']['facet_queries'][$option['filter']]) . ')</span>';

    }
  }

  if (!empty($links)) {
    // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// $output = theme('item_list', array(
//     'items' => $links,
//     'title' => NULL,
//     'type' => 'ul',
//     'attributes' => array('class' => 'islandora-solr-explore'),
//     ));

  }

  return $output;
}

/**
 * Tests for a valid facet query by seeing if the query returns any data.
 */
function islandora_solr_explore_test_facet_query($query) {
  $qp = new IslandoraSolrQueryProcessor();
  $qp->buildQuery('*:*');
  $qp->solrParams['fq'] = $query;
  $qp->solrParams['fl'] = '*';
  $qp->solrParams['facet.limit'] = 1;
  $qp->solrParams['facet.mincount'] = \Drupal::config('islandora_solr.settings')->get('islandora_solr_facet_min_limit');
  $qp->solrParams['facet.field'] = array();
  $qp->solrParams['fl'] = 'PID';
  $qp->executeQuery(FALSE);
}
