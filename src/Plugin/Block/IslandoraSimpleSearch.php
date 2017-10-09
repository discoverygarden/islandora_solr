<?php

namespace Drupal\islandora_solr\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a simple search block.
 *
 * @Block(
 *   id = "islandora_solr_simple_search",
 *   admin_label = @Translation("Islandora simple search"),
 * )
 */
class IslandoraSimpleSearch extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return \Drupal::formBuilder()->getForm('Drupal\islandora_solr\Form\IslandoraSimpleSearch');
  }

}
