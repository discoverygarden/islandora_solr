<?php

/**
 * @file
 * Contains methods to search solr and display results. Depends on
 * Apache_Solr_Php client.
 */

use Drupal\islandora_solr\IslandoraSolrResults;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Extension of IslandoraSolrResults to create an alternative display type.
 */
class IslandoraSolrResultsTable extends IslandoraSolrResults {

  /**
   * Renders the Solr results as a table.
   *
   * @see IslandoraSolrResults::displayResults()
   *
   * @param array $solr_results
   *   The raw Solr results from
   *   IslandoraSolrQueryProcessor::islandoraSolrResult.
   *
   * @return string
   *   Rendered Solr results
   */
  public function printResults($solr_results) {
    $mod_path = drupal_get_path('module', 'islandora_solr_config');
    $display_row_number = \Drupal::config('islandora_solr_config.settings')->get('islandora_solr_table_profile_display_row_no');
    $solr_results = islandora_solr_prepare_solr_results($solr_results);
    $object_results = $solr_results['response']['objects'];
    $object_results = islandora_solr_prepare_solr_doc($object_results);

    $record_start = (int) $solr_results['response']['start'];
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
    return \Drupal::service('renderer')->render($element);
  }

}
