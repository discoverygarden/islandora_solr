<?php

namespace Drupal\islandora_solr\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides block for switching the display.
 *
 * @Block(
 *   id = "islandora_solr_display_switch",
 *   admin_label = @Translation("Islandora displays"),
 * )
 */
class DisplaySwitch extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    module_load_include('inc', 'islandora_solr', 'includes/blocks');
    return islandora_solr_display();
  }

}
