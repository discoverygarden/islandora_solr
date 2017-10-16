<?php

namespace Drupal\islandora_solr\Plugin\Block;

use Drupal\Core\Block\BlockBase;

use Drupal\islandora_solr\IslandoraSolrResults;

/**
 * Provides a block for managing the current query.
 *
 * @Block(
 *   id = "islandora_solr_query",
 *   admin_label = @Translation("Islandora query"),
 * )
 */
class IslandoraSolrQuery extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    global $_islandora_solr_queryclass;
    if (!islandora_solr_results_page($_islandora_solr_queryclass)) {
      return;
    }
    $results = new IslandoraSolrResults();
    $output = $results->currentQuery($_islandora_solr_queryclass);
    if ($output) {
      return ['#markup' => $output];
    }
  }

}
