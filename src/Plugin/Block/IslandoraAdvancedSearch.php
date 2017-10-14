<?php

namespace Drupal\islandora_solr\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an advanced search block.
 *
 * @Block(
 *   id = "islandora_solr_advanced",
 *   admin_label = @Translation("Islandora advanced search"),
 * )
 */
class IslandoraAdvancedSearch extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return \Drupal::formBuilder()->getForm('Drupal\islandora_solr\Form\IslandoraAdvancedSearch');
  }

}
