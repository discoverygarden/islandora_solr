<?php

/**
 * @file
 * Miscellaneous helper functions.
 */

use Drupal\Core\Link;
use Drupal\Core\Cache\CacheableMetadata;

use Drupal\islandora_solr\IslandoraSolrQueryProcessor;

/**
 * Generates the links for the Islandora explore block.
 *
 * @return string
 *   A rendered list containing explore links to search and object counts.
 */
function islandora_solr_explore_generate_links() {
  $function = '_' . __FUNCTION__;
  $elements = $function();
  return \Drupal::service('renderer')->render($elements);
}

/**
 * Generates the links for the Islandora explore block.
 *
 * @return array
 *   A rendered list containing explore links to search and object counts.
 */
function _islandora_solr_explore_generate_links() {
  $config = \Drupal::config('islandora_solr.settings');
  $cache_meta = (new CacheableMetadata())
    ->addCacheableDependency($config);

  $links = [];

  $expore_config = $config->get('islandora_solr_explore_config');
  if (!empty($expore_config)) {
    $qp = new IslandoraSolrQueryProcessor();
    $qp->buildQuery('*:*');
    $qp->solrParams['fl'] = '*';
    $qp->solrParams['facet.limit'] = 100000;
    $qp->solrParams['facet.mincount'] = $config->get('islandora_solr_facet_min_limit');
    $qp->solrParams['facet.field'] = [];
    $qp->solrParams['fl'] = 'PID';
    foreach ($expore_config as $option) {
      $qp->solrParams['facet.query'][] = $option['filter'];
    }
    $qp->executeQuery(FALSE);
    foreach ($expore_config as $option) {
      $links[]['#markup'] = Link::createFromRoute(
        htmlentities($option['label']),
        'islandora_solr.islandora_solr',
        [],
        ['query' => ['f' => [$option['filter']]]])
        ->toString() . '&nbsp;<span>(' . htmlentities($qp->islandoraSolrResult['facet_counts']['facet_queries'][$option['filter']]) . ')</span>';
    }
    $cache_meta->addCacheableDependency($qp);
  }

  $output = !empty($links) ?
    [
      '#theme' => 'item_list',
      '#items' => $links,
      '#title' => NULL,
      '#type' => 'ul',
      '#attributes' => ['class' => ['islandora-solr-explore']],
    ] :
    [];

  $cache_meta->applyTo($output);

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
