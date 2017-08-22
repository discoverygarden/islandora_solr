<?php

/**
 * @file
 * Contains \Drupal\islandora_solr\Form\IslandoraSolrAdminBreadcrumbsSettings.
 */

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class IslandoraSolrAdminBreadcrumbsSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_admin_breadcrumbs_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('islandora_solr.settings');

    foreach (Element::children($form) as $variable) {
      $config->set($variable, $form_state->getValue($form[$variable]['#parents']));
    }
    $config->save();

    if (method_exists($this, '_submitForm')) {
      $this->_submitForm($form, $form_state);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['islandora_solr.settings'];
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form = [];
    $form['islandora_solr_breadcrumbs_admin'] = [
      '#type' => 'fieldset',
      '#title' => t('Breadcrumbs'),
    ];
    // @FIXME
    // l() expects a Url object, created from a route name or external URI.
    // $form['islandora_solr_breadcrumbs_admin']['admin'] = array(
    //     '#type' => 'markup',
    //     '#markup' => l(t('Enable Islandora Solr for Breadcrumbs'), 'admin/islandora/configure'),
    //   );

    $form['islandora_solr_breadcrumbs_admin']['islandora_solr_breadcrumbs_parent_fields'] = [
      '#type' => 'textarea',
      '#title' => t('Solr Parent Fields'),
      '#description' => t('A list of Solr fields containing the PIDs of parent objects,
    one per line. Will search top to bottom and stop on the first hit.'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_breadcrumbs_parent_fields'),
    ];
    $form['islandora_solr_breadcrumbs_admin']['islandora_solr_breadcrumbs_add_collection_query'] = [
      '#type' => 'checkbox',
      '#title' => t('Append query breadcrumbs to collection breadcrumbs'),
      '#description' => t('Appends any additional available breadcrumbs, such as facet breadcrumbs, to the standard collection hierarchy breadcrumbs, if using the Solr collection query backend.'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_breadcrumbs_add_collection_query'),
    ];
    return parent::buildForm($form, $form_state);
  }

}
?>
