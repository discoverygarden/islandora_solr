<?php

namespace Drupal\islandora_solr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Cache\Cache;

use Drupal\islandora_solr\IslandoraSolrResults;
use const Drupal\islandora\Controller\DefaultController\LISTING_TAG;

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
    if (!islandora_solr_results_page($_islandora_solr_queryclass)) {
      return;
    }
    $results = new IslandoraSolrResults();
    $output = $results->displayFacets($_islandora_solr_queryclass);
    if ($output) {
      return $output;
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
      LISTING_TAG,
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
