<?php

namespace Drupal\islandora_solr\Plugin\Block;

use Drupal\islandora\Plugin\Block\AbstractFormBlockBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides an advanced search block.
 *
 * @Block(
 *   id = "islandora_solr_advanced",
 *   admin_label = @Translation("Islandora advanced search"),
 * )
 */
class IslandoraAdvancedSearch extends AbstractFormBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return $this->formBuilder->getForm('Drupal\islandora_solr\Form\IslandoraAdvancedSearch');
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    if ($account->hasPermission('search islandora solr')) {
      return AccessResult::allowed();
    }
    else {
      return AccessResult::forbidden();
    }
  }

}
