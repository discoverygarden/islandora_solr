<?php

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Display admin form for breadcrumb field choice.
 */
class AdminBreadcrumbsSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_admin_breadcrumbs_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['islandora_solr.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['islandora_solr_breadcrumbs_admin'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Breadcrumbs'),
    ];
    $form['islandora_solr_breadcrumbs_admin']['admin'] = [
      '#type' => 'link',
      '#title' => $this->t('Enable Islandora Solr for Breadcrumbs'),
      '#url' => Url::fromRoute('islandora.repository_admin'),
    ];
    $form['islandora_solr_breadcrumbs_admin']['islandora_solr_breadcrumbs_parent_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Solr Parent Fields'),
      '#description' => $this->t('A list of Solr fields containing the PIDs of parent objects,
        one per line. Will search top to bottom and stop on the first hit.'),
      '#default_value' => $this->config('islandora_solr.settings')->get('islandora_solr_breadcrumbs_parent_fields'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('islandora_solr.settings')
      ->set('islandora_solr_breadcrumbs_parent_fields', $form_state->getValue('islandora_solr_breadcrumbs_parent_fields'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
