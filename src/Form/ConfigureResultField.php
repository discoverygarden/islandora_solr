<?php

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form to configure a Solr result field.
 */
class ConfigureResultField extends ConfigFieldFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_configure_result_field_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldType() {
    return 'result_fields';
  }

  /**
   * {@inheritdoc}
   */
  public static function getFieldConfiguration(array $solr_field_settings) {
    module_load_include('inc', 'islandora_solr', 'includes/admin');
    return [
      'label' => isset($solr_field_settings['label']) ? trim($solr_field_settings['label']) : '',
      'snippet' => isset($solr_field_settings['snippet']) ? (bool) $solr_field_settings['snippet'] : FALSE,
      'date_format' => isset($solr_field_settings['date_format']) ? trim($solr_field_settings['date_format']) : '',
      'truncation_type' => isset($solr_field_settings['truncation_type']) ? trim($solr_field_settings['truncation_type']) : 'separate_value_option',
      'maximum_length' => isset($solr_field_settings['maximum_length']) ? (int) trim($solr_field_settings['maximum_length']) : 0,
      'add_ellipsis' => isset($solr_field_settings['add_ellipsis']) ? (bool) $solr_field_settings['add_ellipsis'] : FALSE,
      'wordsafe' => isset($solr_field_settings['wordsafe']) ? (bool) $solr_field_settings['wordsafe'] : FALSE,
      'wordsafe_length' => isset($solr_field_settings['wordsafe_length']) ? (int) $solr_field_settings['wordsafe_length'] : 1,
      'permissions' => empty($solr_field_settings['enable_permissions']) ? _islandora_solr_permissions_default() : $solr_field_settings['permissions'],
      'replace_pid_with_label' => empty($solr_field_settings['replace_pid_with_label']) ? FALSE : (bool) $solr_field_settings['replace_pid_with_label'],
      'link_to_object' => isset($solr_field_settings['link_rendering']) && $solr_field_settings['link_rendering'] == 'object',
      'link_to_search' => isset($solr_field_settings['link_rendering']) && $solr_field_settings['link_rendering'] == 'search',
      'weight' => isset($solr_field_settings['weight']) ? (int) $solr_field_settings['weight'] : 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $solr_field = NULL) {
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/admin');
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/db');
    $form['#prefix'] = '<div id="result_fields_modal">';
    $form['#suffix'] = '</div>';

    $form_state->setStorage(['solr_field' => $solr_field]);
    $values = islandora_solr_get_field_configuration('result_fields', $solr_field);

    $form['options'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['scroll']],
      '#id' => 'islandora-solr-admin-dialog-form',
    ];
    $form['options']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => isset($values['label']) ? $values['label'] : '',
      '#description' => $this->t('A human-readable name.'),
    ];
    $link_options = [
      'none' => $this->t('None'),
      'object' => $this->t("Link this field to the object's page."),
      'search' => $this->t("Link the value to a Solr search result. (NOTE: Will likely break with very large values.)"),
    ];
    $default_link = 'none';

    if ($values['link_to_object']) {
      $default_link = 'object';
    }
    elseif ($values['link_to_search']) {
      $default_link = 'search';
    }
    else {
      $default_link = NULL;
    }
    $form['options']['link_rendering'] = [
      '#type' => 'radios',
      '#title' => $this->t('Linking'),
      '#options' => $link_options,
      '#default_value' => $default_link,
    ];
    $form['options']['snippet'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Highlight'),
      '#default_value' => isset($values['snippet']) ? $values['snippet'] : FALSE,
      '#description' => $this->t('If a match is found on this field, the search term will be highlighted.<br /><strong>Note:</strong> Only text that has been both indexed and stored may be highlighted. While highlighting on non-tokenized fields is possible, the best results are achieved using tokenized fields. This checkbox may be grayed out if the Solr field cannot be highlighted.'),
    ];
    $highlighting_allowed = islandora_solr_check_highlighting_allowed($solr_field);
    if ($highlighting_allowed == FALSE) {
      $form['options']['snippet']['#default_value'] = 0;
      $form['options']['snippet']['#disabled'] = TRUE;
    }

    $form['options']['replace_pid_with_label'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Replace PID with Object Label'),
      '#default_value' => isset($values['replace_pid_with_label']) ? $values['replace_pid_with_label'] : FALSE,
      '#description' => $this->t("Replace a PID (islandora:foo) or a URI (info:fedora/islandora:foo) with that object's label. Will only work with non-tokenized Solr fields (full literal strings)."),
    ];

    if (islandora_solr_is_date_field($solr_field)) {
      $form['options']['date_format'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Date format'),
        '#default_value' => isset($values['date_format']) ? $values['date_format'] : '',
        '#description' => $this->t('The format of the date, as it will be displayed in the search results. Use <a href="@url" target="_blank">PHP date()</a> formatting. Works best when the date format matches the granularity of the source data. Otherwise it is possible that there will be duplicates displayed.', ['@url' => 'http://php.net/manual/function.date.php']),
      ];
    }

    $form['options']['max_length_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Maximum Length'),
      '#description' => $this->t('<strong>Note:</strong> Truncation can lead to unexpected results when used in secondary display profiles such as CSV and RSS.'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      'truncation_type' => [
        '#type' => 'radios',
        '#title' => $this->t('Truncation Type'),
        '#options' => ['separate_value_option' => $this->t('Limit length of each separate value'), 'whole_field_option' => $this->t('Limit Length of the whole field')],
        '#default_value' => isset($values['truncation_type']) ? $values['truncation_type'] : 'separate_value_option',
      ],
      'maximum_length' => [
        '#type' => 'number',
        '#min' => 0,
        '#title' => $this->t('Maximum Length'),
        '#default_value' => isset($values['maximum_length']) ? $values['maximum_length'] : '0',
        '#description' => $this->t('Maximum field length to render for display. A setting of 0 (default) renders the entire value.<br /> When truncating based on the whole field the max length may be exceeded by the length of ellispse string.'),
      ],
      'add_ellipsis' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Add Ellipsis'),
        '#description' => $this->t('Add ... to the end of the truncated string.'),
        '#default_value' => isset($values['add_ellipsis']) ? $values['add_ellipsis'] : FALSE,
        '#states' => [
          'invisible' => [
            ':input[name="maximum_length"]' => ['value' => '0'],
          ],
        ],
      ],
      'wordsafe' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Wordsafe'),
        '#description' => $this->t('If selected attempt to truncate on a word boundary. See <a href="@url" target="_blank".>documentation</a> for more information.', ['@url' => 'https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Component%21Utility%21Unicode.php/function/Unicode%3A%3Atruncate/8.2.x']),
        '#default_value' => isset($values['wordsafe']) ? $values['wordsafe'] : FALSE,
        '#states' => [
          'invisible' => [
            ':input[name="maximum_length"]' => ['value' => '0'],
          ],
        ],
      ],
      'wordsafe_length' => [
        '#type' => 'textfield',
        '#title' => $this->t('Minimum Wordsafe Length'),
        '#description' => $this->t('The minimum acceptable length for truncation.'),
        '#states' => [
          'invisible' => [
            [':input[name="maximum_length"]' => ['value' => '0']],
            [':input[name="wordsafe"]' => ['checked' => FALSE]],
          ],
        ],
        '#default_value' => isset($values['wordsafe_length']) ? $values['wordsafe_length'] : 1,
      ],
    ];

    $this->appendPermissionsAndActions($values, $form, $form_state, TRUE, [$this, 'modalSubmit']);

    return $form;
  }

}
