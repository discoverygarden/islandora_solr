<?php

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form to configure a Solr result field.
 */
class IslandoraSolrConfigureResultField extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_configure_result_field_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/admin');

    $form_state['dialog'] = $variables;

    $solr_field = $variables['solr_field'];
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
    $link_options = array(
      'none' => t('None'),
      'object' => t("Link this field to the object's page."),
      'search' => t("Link the value to a Solr search result. (NOTE: Will likely break with very large values.)"),
    );
    $default_link = 'none';

    if (isset($values['link_rendering'])) {
      $default_link = $values['link_rendering'];
    }
    elseif (isset($values['link_to_object']) && $values['link_to_object'] != FALSE) {
      $default_link = 'object';
    }
    elseif (isset($values['link_to_search']) && $values['link_to_search'] != FALSE) {
      $default_link = 'search';
    }
    $form['options']['link_rendering'] = array(
      '#type' => 'radios',
      '#title' => t('Linking'),
      '#options' => $link_options,
      '#default_value' => $default_link,
    );
    $form['options']['snippet'] = array(
      '#type' => 'checkbox',
      '#title' => t('Highlight'),
      '#default_value' => isset($values['snippet']) ? $values['snippet'] : FALSE,
      '#description' => t('If a match is found on this field, the search term will be highlighted.<br /><strong>Note:</strong> Only text that has been both indexed and stored may be highlighted. While highlighting on non-tokenized fields is possible, the best results are achieved using tokenized fields. This checkbox may be grayed out if the Solr field cannot be highlighted.'),
    );
    $highlighting_allowed = islandora_solr_check_highlighting_allowed($solr_field);
    if ($highlighting_allowed == FALSE) {
      $form['options']['snippet']['#default_value'] = 0;
      $form['options']['snippet']['#disabled'] = TRUE;
    }

    if (islandora_solr_is_date_field($solr_field)) {
      $form['options']['date_format'] = array(
        '#type' => 'textfield',
        '#title' => t('Date format'),
        '#default_value' => isset($values['date_format']) ? $values['date_format'] : '',
        '#description' => t('The format of the date, as it will be displayed in the search results. Use <a href="@url" target="_blank">PHP date()</a> formatting. Works best when the date format matches the granularity of the source data. Otherwise it is possible that there will be duplicates displayed.', array('@url' => 'http://php.net/manual/function.date.php')),
      );
    }

    $form['options']['max_length_fieldset'] = array(
      '#type' => 'fieldset',
      '#title' => t('Maximum Length'),
      '#description' => t('<strong>Note:</strong> Truncation can lead to unexpected results when used in secondary display profiles such as CSV and RSS.'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      'truncation_type' => array(
        '#type' => 'radios',
        '#title' => t('Truncation Type'),
        '#options' => array('separate_value_option' => t('Limit length of each separate value'), 'whole_field_option' => t('Limit Length of the whole field')),
        '#default_value' => isset($values['truncation_type']) ? $values['truncation_type'] : 'separate_value_option',
      ),
      'maximum_length' => array(
        '#type' => 'textfield',
        '#title' => t('Maximum Length'),
        '#default_value' => isset($values['maximum_length']) ? $values['maximum_length'] : '0',
        '#element_validate' => array('element_validate_integer'),
        '#description' => t('Maximum field length to render for display. A setting of 0 (default) renders the entire value.<br /> When truncating based on the whole field the max length may be exceeded by the length of ellispse string.'),
      ),
      'add_ellipsis' => array(
        '#type' => 'checkbox',
        '#title' => t('Add Ellipsis'),
        '#description' => t('Add ... to the end of the truncated string.'),
        '#default_value' => isset($values['add_ellipsis']) ? $values['add_ellipsis'] : FALSE,
        '#states' => array(
          'invisible' => array(
            ':input[name="maximum_length"]' => array('value' => '0'),
          ),
        ),
      ),
      'wordsafe' => array(
        '#type' => 'checkbox',
        '#title' => t('Wordsafe'),
        '#description' => t('If selected attempt to truncate on a word boundary. See <a href="@url" target="_blank".>documentation</a> for more information.', array('@url' => 'https://api.drupal.org/api/drupal/includes!unicode.inc/function/truncate_utf8/7')),
        '#default_value' => isset($values['wordsafe']) ? $values['wordsafe'] : FALSE,
        '#states' => array(
          'invisible' => array(
            ':input[name="maximum_length"]' => array('value' => '0'),
          ),
        ),
      ),
      'wordsafe_length' => array(
        '#type' => 'textfield',
        '#title' => t('Minimum Wordsafe Length'),
        '#description' => t('The minimum acceptable length for truncation.'),
        '#states' => array(
          'invisible' => array(
            array(':input[name="maximum_length"]' => array('value' => '0')),
            array(':input[name="wordsafe"]' => array('checked' => FALSE)),
          ),
        ),
        '#default_value' => isset($values['wordsafe_length']) ? $values['wordsafe_length'] : 1,
      ),
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
