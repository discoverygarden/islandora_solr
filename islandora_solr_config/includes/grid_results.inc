<?php

/**
 * @file
 * Class override for grid display.
 */

use Drupal\Core\Link;
use Drupal\Core\Url;

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
  public function printResults(array $solr_results) {
    $object_results = $solr_results['response']['objects'];

    $elements = [];
    $elements['solr_total'] = $solr_results['response']['numFound'];
    $elements['solr_start'] = $solr_results['response']['start'];

    foreach ($object_results as $key => $result) {
      if (isset($result['object_label'])) {
        $options['attributes']['title'] = $result['object_label'];
      }
      if (isset($result['object_url_params'])) {
        $options['query'] = $result['object_url_params'];
      }
      if (isset($result['object_url_fragment'])) {
        $options['fragment'] = $result['object_url_fragment'];
      }
      // Thumbnail link.
      $image = [
        '#theme' => 'image',
        '#uri' => Url::fromUri($result['thumbnail_url'], ['query' => $result['thumbnail_url_params']])->toString(),
      ];
      if (isset($result['object_label'])) {
        $image['#alt'] = $result['object_label'];
      }
      $object_results[$key]['thumbnail_link'] = Link::fromTextAndUrl(
        \Drupal::service('renderer')->render($image),
        Url::fromUri($result['object_url'], $options)
      );
      $object_results[$key]['label_link'] = Link::fromTextAndUrl(
        $result['object_label'],
        Url::fromUri($result['object_url'], $options)
      );
    }
    $element = [
      '#theme' => 'islandora_solr_grid',
      '#results' => $object_results,
      '#elements' => $elements,
      '#attached' => ['library' => ['islandora_solr_config/theme', 'islandora_solr/islandora-solr-theme']],
    ];
    return \Drupal::service('renderer')->render($element);
  }

}
