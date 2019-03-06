<?php

namespace Drupal\islandora_solr\Plugin\Block;

use Drupal\islandora\Plugin\Block\AbstractFormBlockBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Cache\Cache;

/**
 * Provides a simple search block.
 *
 * @Block(
 *   id = "islandora_solr_simple_search",
 *   admin_label = @Translation("Islandora simple search"),
 * )
 */
class IslandoraSimpleSearch extends AbstractFormBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return $this->formBuilder->getForm('Drupal\islandora_solr\Form\IslandoraSimpleSearch');
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
