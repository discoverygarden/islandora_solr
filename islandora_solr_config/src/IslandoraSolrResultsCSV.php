<?php
namespace Drupal\islandora_solr_config;

/**
 * Extention of IslandoraSolrResults to create an alternative display type.
 */
class IslandoraSolrResultsCSV extends IslandoraSolrResults {

  /**
   * Renders the Solr results as a comma separated values file (.csv).
   *
   * Renders the Solr results as a comma separated values file (.csv). Resets
   * the html headers so it'll prompt to be downloaded.
   *
   * @see IslandoraSolrResults
   *
   * @global type $base_url
   *
   * @param object $islandora_solr_query
   *   The IslandoraSolrQueryProcessor object which includes the current query
   *   settings and the raw Solr results.
   */
  public function printCSV($islandora_solr_query) {
    global $base_url;
    $redirect = \Drupal\Component\Utility\UrlHelper::parse(request_uri());
    // We want to unset the display profile of CSV, but keep everything else.
    $params = $redirect['query'];
    unset($params['solr_profile']);
    $redirect_url = \Drupal\Core\Url::fromRoute("<current>")->toString();
    batch_set($this->batchSolrResults($islandora_solr_query));
    batch_process(array(
      $redirect_url,
      array(
        'query' => $params,
      ),
    ));
  }

  /**
   * Constructs a batch that creates a CSV for export.
   *
   * @param object $islandora_solr_query
   *   The Islandora Solr Query processor.
   */
  public function batchSolrResults($islandora_solr_query) {
    return array(
      'operations' => array(
        array('islandora_solr_config_csv_batch_update_operation', array($islandora_solr_query)),
      ),
      'title' => t('Exporting search results as CSV...'),
      'init_message' => t('Preparing to construct CSV.'),
      'progress_message' => t('Time elapsed: @elapsed <br/>Estimated time remaining @estimate.'),
      'error_message' => t('An error has occurred.'),
      'file' => drupal_get_path('module', 'islandora_solr_config') . '/includes/csv_results.inc',
      'finished' => 'islandora_solr_config_csv_download_csv',
    );
  }
}
