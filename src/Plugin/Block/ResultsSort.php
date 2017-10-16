<?php

namespace Drupal\islandora_solr\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a results sorting block.
 *
 * @Block(
 *   id = "islandora_solr_sort",
 *   admin_label = @Translation("Islandora sort"),
 * )
 */
class ResultsSort extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    module_load_include('inc', 'islandora_solr', 'includes/blocks');
    $sort = islandora_solr_sort();
    if ($sort) {
      return ['#markup' => islandora_solr_sort()];
    }
  }

}
