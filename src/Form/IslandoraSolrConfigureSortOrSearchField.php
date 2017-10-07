<?php

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form to configure a Solr sort or search field.
 */
class IslandoraSolrConfigureSortOrSearchField extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_configure_sort_or_search_field_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/admin');

    $form_state['dialog'] = $variables;

    $values = $variables['values'];
    $form['options'] = array(
      '#type' => 'container',
      '#attributes' => array('class' => array('scroll')),
      '#id' => 'islandora-solr-admin-dialog-form',
    );
    $form['options']['label'] = array(
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#default_value' => isset($values['label']) ? $values['label'] : '',
      '#description' => t('A human-readable name.'),
    );
    islandora_solr_append_permissions_and_actions($values, $form);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
