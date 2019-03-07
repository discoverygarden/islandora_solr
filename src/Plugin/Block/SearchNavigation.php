<?php

namespace Drupal\islandora_solr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

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
    return _islandora_solr_search_navigation();
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'search islandora solr');
  }

}
