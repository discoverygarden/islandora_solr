<?php

namespace Drupal\islandora_solr\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block for navigating back to searches from results.
 *
 * @Block(
 *   id = "islandora_solr_search_navigation",
 *   admin_label = @Translation("Islandora search navigation"),
 * )
 */
class SearchNavigation extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    module_load_include('inc', 'islandora_solr', 'includes/blocks');
    return islandora_solr_search_navigation();
  }

}
