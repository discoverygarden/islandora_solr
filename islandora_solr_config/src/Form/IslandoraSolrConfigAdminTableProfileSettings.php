<?php

/**
 * @file
 * Contains \Drupal\islandora_solr_config\Form\IslandoraSolrConfigAdminTableProfileSettings.
 */

namespace Drupal\islandora_solr_config\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

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

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {

    // @FIXME
// // @FIXME
// // This looks like another module's variable. You'll need to rewrite this call
// // to ensure that it uses the correct configuration object.
// $form['options']['islandora_solr_table_profile_settings'] = array(
//     '#type' => 'fieldset',
//     '#title' => t('General Table Settings'),
//     '#collapsible' => TRUE,
//     '#collapsed' => FALSE,
//     'islandora_solr_table_profile_display_row_no' => array(
//       '#type' => 'checkbox',
//       '#title' => t('Display Row Numbers?'),
//       '#default_value' => variable_get('islandora_solr_table_profile_display_row_no', 1),
//       '#description' => t('Should row numbers be rendered as a column in the results table?'),
//     ),
//     'islandora_solr_table_profile_table_class' => array(
//       '#type' => 'textfield',
//       '#title' => t('Table Class'),
//       '#default_value' => variable_get('islandora_solr_table_profile_table_class', ''),
//       '#description' => t('A class string to set for the table element, if any.'),
//     ),
//   );

    $form['buttons']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
    ];

    return $form;
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // @FIXME
// // @FIXME
// // This looks like another module's variable. You'll need to rewrite this call
// // to ensure that it uses the correct configuration object.
// variable_set('islandora_solr_table_profile_display_row_no', $form_state['values']['islandora_solr_table_profile_display_row_no']);

    // @FIXME
// // @FIXME
// // This looks like another module's variable. You'll need to rewrite this call
// // to ensure that it uses the correct configuration object.
// variable_set('islandora_solr_table_profile_table_class', $form_state['values']['islandora_solr_table_profile_table_class']);

    drupal_set_message(t('The Solr table profile configuration options have been saved.'));
  }

}
