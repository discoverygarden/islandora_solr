<?php

namespace Drupal\islandora_solr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Cache\CacheableMetadata;

use Drupal\islandora_solr\IslandoraSolrResults;

/**
 * Provides a faceting block.
 *
 * @Block(
 *   id = "islandora_solr_basic_facets",
 *   admin_label = @Translation("Islandora facets"),
 * )
 */
class Facets extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    global $_islandora_solr_queryclass;

    $cache_meta = (new CacheableMetadata())
      ->addCacheableDependency($_islandora_solr_queryclass)
      ->addCacheContexts([
        'url',
      ]);

    $output = [];

    if (islandora_solr_results_page($_islandora_solr_queryclass)) {
      $results = new IslandoraSolrResults();
      $output += $results->displayFacets($_islandora_solr_queryclass);
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
