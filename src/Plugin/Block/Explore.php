<?php

namespace Drupal\islandora_solr\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block for exploring objects through facets.
 *
 * @Block(
 *   id = "islandora_solr_explore",
 *   admin_label = @Translation("Islandora explore"),
 * )
 */
class Explore extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    module_load_include('inc', 'islandora_solr', 'includes/explore');
    return islandora_solr_explore_generate_links();
  }

}
