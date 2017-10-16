<?php

/**
 * @file
 * Miscellaneous helper functions.
 */

use Drupal\Core\Link;

use Drupal\islandora_solr\IslandoraSolrQueryProcessor;

/**
 * Generates the links for the Islandora explore block.
 *
 * @return output
 *  A rendered list containing explore links to islandora solr search and
 *  object counts.
 */
function islandora_solr_explore_generate_links() {
  $output = '';
  $links = [];
  $expore_config = \Drupal::config('islandora_solr.settings')->get('islandora_solr_explore_config');
  if (!empty($expore_config)) {
    $qp = new IslandoraSolrQueryProcessor();
    $qp->buildQuery('*:*');
    $qp->solrParams['fl'] = '*';
    $qp->solrParams['facet.limit'] = 100000;
    $qp->solrParams['facet.mincount'] = \Drupal::config('islandora_solr.settings')->get('islandora_solr_facet_min_limit');
    $qp->solrParams['facet.field'] = [];
    $qp->solrParams['fl'] = 'PID';
    foreach ($expore_config as $index => $option) {
      $qp->solrParams['facet.query'][] = $option['filter'];
    }
    $qp->executeQuery(FALSE);
    foreach ($expore_config as $index => $option) {
      $url = htmlspecialchars(urlencode($option['filter']));
      $links[]['#markup'] = Link::createFromRoute(
        htmlentities($option['label']),
        'islandora_solr.islandora_solr',
        ['query' => $url])
      ->toString() . '&nbsp;<span>(' . htmlentities($qp->islandoraSolrResult['facet_counts']['facet_queries'][$option['filter']]) . ')</span>';
    }
  }

  if (!empty($links)) {
    $list = [
      '#theme' => 'item_list',
      '#items' => $links,
      '#title' => NULL,
      '#type' => 'ul',
      '#attributes' => ['class' => ['islandora-solr-explore']],
    ];
    $output = \Drupal::service('renderer')->render($list);
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
  $qp->solrParams['facet.field'] = [];
  $qp->solrParams['fl'] = 'PID';
  $qp->executeQuery(FALSE);
}
