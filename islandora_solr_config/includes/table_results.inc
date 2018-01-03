<?php

/**
 * @file
 * Contains methods to search solr and display results.
 */

use Drupal\islandora_solr\IslandoraSolrResults;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Extension of IslandoraSolrResults to create an alternative display type.
 *
 * @codingStandardsIgnoreStart
 * XXX: Poorly implemented sniff indicates class should begin with
 * "IslandoraSolrConfig.info" instead of just "IslandoraSolrConfig"... ideally,
 * this should move somewhere under the Drupal\islandora_solr_config namespace
 * at somepoint...
 */
class IslandoraSolrConfigResultsTable extends IslandoraSolrResults {
// @codingStandardsIgnoreEnd

  /**
   * Renders the Solr results as a table.
   *
   * @param array $solr_results
   *   The raw Solr results from
   *   IslandoraSolrQueryProcessor::islandoraSolrResult.
   *
   * @see IslandoraSolrResults::displayResults()
   *
   * @return string
   *   Rendered Solr results
   */
  public function printResults(array $solr_results) {
    module_load_include('inc', 'islandora_solr', 'includes/db');
    $display_row_number = \Drupal::config('islandora_solr_config.settings')->get('islandora_solr_table_profile_display_row_no');
    $solr_results = islandora_solr_prepare_solr_results($solr_results);
    $object_results = $solr_results['response']['objects'];
    $object_results = islandora_solr_prepare_solr_doc($object_results);

    $fields = $this->resultFieldArray;
    $empty = [];
    foreach ($fields as $field => $label) {
      $empty[$field] = TRUE;
    }
    $rows = [];
    foreach ($object_results as $key => $object_result) {
      $row = [];
      $doc = $object_result['solr_doc'];
      if ($display_row_number == 1) {
        $row['#'] = Link::fromTextAndUrl(($key + 1), Url::fromUri($object_result['object_url'], ['query' => $object_result['object_url_params']]));
      }

      foreach ($fields as $field => $field_label) {
        if (isset($doc[$field]['value'])) {
          $value = $doc[$field]['value'];
          $row[$field] = $value;
          $empty[$field] = FALSE;
        }
        else {
          $row[$field] = NULL;
        }
      }
      $rows[] = $row;
    }
    $header = [];
    if ($display_row_number == 1) {
      $header['#'] = '#';
    }

    $header += $fields;

    // Filter empty columns.
    foreach ($empty as $field => $bool) {
      if ($bool == TRUE) {
        unset($header[$field]);
        foreach ($rows as $key => $row) {
          unset($rows[$key][$field]);
        }
      }
    }
    $element = [
      '#theme' => 'islandora_solr_table',
      '#header' => $header,
      '#rows' => $rows,
      '#table_class' => \Drupal::config('islandora_solr_config.settings')->get('islandora_solr_table_profile_table_class'),
      '#attached' => ['library' => ['islandora_solr_config/theme', 'islandora_solr/islandora-solr-theme']],
    ];
    if (islandora_solr_get_truncate_length_fields()) {
      $element['#attached']['library'][] = 'islandora_solr/toggle';
    }
    return \Drupal::service('renderer')->render($element);
  }

}
