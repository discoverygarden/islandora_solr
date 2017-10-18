<?php

/**
 * @file
 * Class override for grid display
 */

use Drupal\islandora_solr\IslandoraSolrResults;

/**
 * Extension of IslandoraSolrResults to create an alternative display type.
 */
class IslandoraSolrResultsGrid extends IslandoraSolrResults {

  /**
   * Renders the Solr results as a responsive grid view.
   *
   * Markup and styling is based on the Islandora collection grid view. Some
   * styling is inherited from it.
   *
   * @see IslandoraSolrResults::displayResults()
   *
   * @param array $solr_results
   *   The processed Solr results from
   *   IslandoraSolrQueryProcessor::islandoraSolrResult.
   *
   * @return string
   *   Rendered Solr results.
   */
  public function printResults($solr_results) {
    $mod_path = drupal_get_path('module', 'islandora_solr_config');
    $object_results = $solr_results['response']['objects'];

    $elements = [];
    $elements['solr_total'] = $solr_results['response']['numFound'];
    $elements['solr_start'] = $solr_results['response']['start'];

    $element = [
      '#theme' => 'islandora_solr_grid',
      '#results' => $object_results,
      '#elements' => $elements,
      '#attached' => ['library' => ['islandora_solr_config/theme']],
    ];
    return \Drupal::service('renderer')->render($element);
  }
}
