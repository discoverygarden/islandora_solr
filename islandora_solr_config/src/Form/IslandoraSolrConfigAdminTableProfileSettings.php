<?php

namespace Drupal\islandora_solr_config\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class for the table profile admin settings form.
 */
class IslandoraSolrConfigAdminTableProfileSettings extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_config_admin_table_profile_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['options']['islandora_solr_table_profile_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('General Table Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      'islandora_solr_table_profile_display_row_no' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Display Row Numbers?'),
        '#default_value' => self::config('islandora_solr_config.settings')->get('islandora_solr_table_profile_display_row_no'),
        '#description' => $this->t('Should row numbers be rendered as a column in the results table?'),
      ],
      'islandora_solr_table_profile_table_class' => [
        '#type' => 'textfield',
        '#title' => $this->t('Table Class'),
        '#default_value' => self::config('islandora_solr_config.settings')->get('islandora_solr_table_profile_table_class'),
        '#description' => $this->t('A class string to set for the table element, if any.'),
      ],
    ];

    $form['buttons']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('islandora_solr_config.settings');

    $config->set(
      'islandora_solr_table_profile_display_row_no',
      $form_state->getValue('islandora_solr_table_profile_display_row_no')
    );
    $config->set(
      'islandora_solr_table_profile_table_class',
      $form_state->getValue('islandora_solr_table_profile_table_class')
    );

    $config->save();

    drupal_set_message($this->t('The Solr table profile configuration options have been saved.'));
  }

}
