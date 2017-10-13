<?php

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;

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

    if (isset($values['link_rendering'])) {
      $default_link = $values['link_rendering'];
    }
    elseif (isset($values['link_to_object']) && $values['link_to_object'] != FALSE) {
      $default_link = 'object';
    }
    elseif (isset($values['link_to_search']) && $values['link_to_search'] != FALSE) {
      $default_link = 'search';
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

    islandora_solr_append_permissions_and_actions($values, $form, TRUE, [$this, 'modalSubmit']);

    return $form;
  }

  /**
   * Non-reloading ajax submit handler.
   */
  public function modalSubmit(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#result_fields_modal', $form));
    }
    else {
      $response->addCommand(new OpenModalDialogCommand($this->t('Saved'), $this->t('The configuration has been saved.'), ['width' => 800]));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/admin');
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/db');
    $settings = _islandora_solr_handle_solr_field_settings($form_state->getValues(), 'result_fields');
    islandora_solr_set_field_configuration('result_fields', $form_state->getStorage()['solr_field'], $settings);
  }

}
