<?php

/**
 * @file
 * Contains \Drupal\islandora_solr\Form\IslandoraSolrAdminSettingsDefaultConfirmForm.
 */

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Confirmation for reset solr settings.
 */
class IslandoraSolrAdminSettingsDefaultConfirmForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_admin_settings_default_confirm_form';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form = confirm_form($form, t('Confirm settings reset to default'), 'admin/islandora/search/islandora_solr/settings/', t('Confirm reset settings to default, this cannot be undone.'), t('Continue'), t('Cancel'));
    return $form;
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    module_load_include('install', 'islandora_solr');
    $vars = islandora_solr_search_settings_variables();
    array_walk($vars, 'variable_del');
    db_delete('islandora_solr_fields')->execute();
    drupal_set_message(t('The configuration options have been reset to their default values.'));
    $form_state->set(['redirect'], 'admin/islandora/search/islandora_solr/settings/');
  }

}
