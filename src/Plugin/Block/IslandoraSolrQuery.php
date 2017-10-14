<?php

namespace Drupal\islandora_solr\Plugin\Block;

use Drupal\Core\Block\BlockBase;

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
    module_load_include('inc', 'islandora_solr', 'includes/results');
    return IslandoraSolrResults::currentQuery();
  }

}
