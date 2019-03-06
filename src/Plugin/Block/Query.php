<?php

namespace Drupal\islandora_solr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Cache\Cache;

use Drupal\islandora_solr\IslandoraSolrResults;

/**
 * Provides a block for managing the current query.
 *
 * @Block(
 *   id = "islandora_solr_query",
 *   admin_label = @Translation("Islandora query"),
 * )
 */
class Query extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    global $_islandora_solr_queryclass;
    if (!islandora_solr_results_page($_islandora_solr_queryclass)) {
      return;
    }
    $results = new IslandoraSolrResults();
    $output = $results->currentQuery($_islandora_solr_queryclass);
    if ($output) {
      return ['#markup' => $output];
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
