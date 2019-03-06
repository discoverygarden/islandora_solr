<?php

namespace Drupal\islandora_solr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Cache\Cache;

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
      return ['#markup' => $sort];
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
      'config:islandora_solr.fields',
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
