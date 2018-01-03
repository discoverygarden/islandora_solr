<?php

/**
 * @file
 * Contains methods to search Solr and display results.
 *
 * Depends on Apache_Solr_Php client.
 */

use Drupal\Core\Link;
use Drupal\Core\Url;

use Drupal\islandora_solr\IslandoraSolrResults;

/**
 * Extention of IslandoraSolrResults to create an alternative display type.
 *
 * @codingStandardsIgnoreStart
 * XXX: Poorly implemented sniff indicates class should begin with
 * "IslandoraSolrConfig.info" instead of just "IslandoraSolrConfig"... ideally,
 * this should move somewhere under the Drupal\islandora_solr_config namespace
 * at somepoint...
 */
class IslandoraSolrConfigResultsCSV extends IslandoraSolrResults {
// @codingStandardsIgnoreEnd

  /**
   * Renders the Solr results as a comma separated values file (.csv).
   *
   * Renders the Solr results as a comma separated values file (.csv). Resets
   * the html headers so it'll prompt to be downloaded.
   *
   * @param object $islandora_solr_query
   *   The IslandoraSolrQueryProcessor object which includes the current query
   *   settings and the raw Solr results.
   */
  public function printCsv($islandora_solr_query) {
    // We want to unset the display profile of CSV, but keep everything else.
    $params = \Drupal::request()->query->all();
    unset($params['solr_profile']);
    batch_set($this->batchSolrResults($islandora_solr_query));
    return batch_process(Url::fromRoute('<current>', ['query' => $params])->toString());
  }

  /**
   * Constructs a batch that creates a CSV for export.
   *
   * @param object $islandora_solr_query
   *   The Islandora Solr Query processor.
   */
  public function batchSolrResults($islandora_solr_query) {
    return [
      'operations' => [
        ['islandora_solr_config_csv_batch_update_operation', [$islandora_solr_query]],
      ],
      'title' => t('Exporting search results as CSV...'),
      'init_message' => t('Preparing to construct CSV.'),
      'progress_message' => t('Time elapsed: @elapsed <br/>Estimated time remaining @estimate.'),
      'error_message' => t('An error has occurred.'),
      'file' => drupal_get_path('module', 'islandora_solr_config') . '/includes/csv_results.inc',
      'finished' => 'islandora_solr_config_csv_download_csv',
    ];
  }

}

/**
 * Constructs the update operation for the CSV search result export.
 *
 * Batch in 1000 increments so Solr doesn't go kaboooom.
 *
 * @param object $islandora_solr_query
 *   An Islandora Solr Query processor object.
 * @param array $context
 *   The context array for the current batch.
 */
function islandora_solr_config_csv_batch_update_operation($islandora_solr_query, array &$context) {
  $csv_batch_limit = 100;
  $sandbox = &$context['sandbox'];

  if (!isset($sandbox['offset'])) {
    $csv_file_uri = \Drupal::service("file_system")->tempnam('temporary://', 'csv');
    $context['results']['islandora_solr_csv_file'] = $csv_file_uri;
    $sandbox['offset'] = 0;
    $context['results']['islandora_solr_csv_headers'] = [];
    $sandbox['total'] = $islandora_solr_query->islandoraSolrResult['response']['numFound'];
    if ($sandbox['total'] === 0) {
      return;
    }
  }
  $context['message'] = t('Processing results @start to @end.', [
    '@start' => $sandbox['offset'],
    '@end' => min($sandbox['offset'] + $csv_batch_limit, $sandbox['total']),
  ]);
  $fp = fopen($context['results']['islandora_solr_csv_file'], 'a');
  // Set the starting offset for this iteration.
  $islandora_solr_query->solrStart = $sandbox['offset'];
  $islandora_solr_query->solrLimit = $csv_batch_limit;
  $islandora_solr_query->executeQuery();
  $response_data = $islandora_solr_query->islandoraSolrResult['response'];
  $object_results = $response_data['objects'];
  // We do this to limit results to just the fields defined in the solr config
  // if "limit results" is selected. If it's not we will return all the fields.
  $object_results = islandora_solr_prepare_solr_doc($object_results);
  $headers = &$context['results']['islandora_solr_csv_headers'];
  foreach ($object_results as $object) {
    $csv_array = [];
    foreach ($headers as $field => $label) {
      $csv_array[] = isset($object['solr_doc'][$field]['value']) ?
        implode('; ', (array) $object['solr_doc'][$field]['value']) :
        '';
    }
    foreach (array_diff_key($object['solr_doc'], $headers) as $field => $values) {
      $headers[$field] = $values['label'];
      $csv_array[] = implode('; ', (array) $values['value']);
    }
    fputcsv($fp, $csv_array);
  }
  fclose($fp);

  $sandbox['offset'] += $csv_batch_limit;
  $context['finished'] = $sandbox['offset'] / $sandbox['total'];
}

/**
 * Copies file from tmp and appends headers on the front for export.
 *
 * @param bool $success
 *   The success array from the batch.
 * @param array $results
 *   The results array from the batch.
 * @param array $operations
 *   The operations array from the batch.
 */
function islandora_solr_config_csv_download_csv($success, array $results, array $operations) {
  $user = \Drupal::currentUser();
  $export_uri = 'public://csv_export_' . time() . '.csv';
  // We register this file into Drupal so it will be deleted.
  $file = new stdClass();
  $file->fid = NULL;
  $file->uri = $export_uri;
  $file->filename = \Drupal::service("file_system")->basename($export_uri);
  $file->filemime = \Drupal::service('file.mime_type.guesser')->guess($file->uri);
  $file->uid = $user->id();
  $fp = fopen($export_uri, 'w');
  fputcsv($fp, $results['islandora_solr_csv_headers']);

  $tmp_file = fopen($results['islandora_solr_csv_file'], 'r');
  stream_copy_to_stream($tmp_file, $fp);
  fclose($fp);
  fclose($tmp_file);
  file_unmanaged_delete($results['islandora_solr_csv_file']);
  drupal_set_message(t('The exported results CSV is available for download @url.', [
    '@url' => Link::fromTextAndUrl(t('here'), Url::fromUri(file_create_url($export_uri)))->toString(),
  ]));
}
