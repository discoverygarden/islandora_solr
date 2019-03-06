<?php

namespace Drupal\islandora_solr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Cache\Cache;

/**
 * Provides a block for choosing the paging size of results.
 *
 * @Block(
 *   id = "islandora_solr_result_limit",
 *   admin_label = @Translation("Islandora search result limit"),
 * )
 */
class ResultLimit extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    module_load_include('inc', 'islandora_solr', 'includes/blocks');
    $limits = islandora_solr_search_results_limit();
    if ($limits) {
      return ['#markup' => $limits];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'search islandora solr');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), [
      'config:islandora_solr.settings',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), [
      'user',
      'url',
      'languages',
    ]);
  }

}
