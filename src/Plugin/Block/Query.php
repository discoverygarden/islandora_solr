<?php

namespace Drupal\islandora_solr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Cache\CacheableMetadata;

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

    $cache_meta = (new CacheableMetadata())
      ->addCacheContexts([
        'url',
      ]);

    $output = [];

    if (islandora_solr_results_page($_islandora_solr_queryclass)) {
      $cache_meta->addCacheableDependency($_islandora_solr_queryclass);
      $results = new IslandoraSolrResults();
      $output_candidate = $results->currentQuery($_islandora_solr_queryclass);
      if ($output_candidate) {
        $output['#markup'] = $output_candidate;
      }
    }

    $cache_meta->applyTo($output);

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'search islandora solr');
  }

}
