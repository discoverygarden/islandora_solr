<?php

/**
 * @file
 * Contains administration related helpers.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Generates fields for the admin fields table.
 *
 * First collects values to populate the table, then render the fields.
 *
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The form_state array of the form. Includes submitted values.
 * @param array $terms
 *   Terms array passed by reference. A base form element for the table is
 *   already created and new form elements are added in this function.
 * @param string $field_type
 *   An identifier for the field table which is being created.
 */
function islandora_solr_admin_settings_fields(FormStateInterface $form_state, &$terms, $field_type) {
  // Set variables.
  $field_type_class = str_replace('_', '-', $field_type);
  // Ways to populate the fields table:
  // 1. Saved values from the DB
  // 2. Triggered element
  // 2.a. Add field
  // 2.b. Remove field
  // 1. Saved values from the DB.
  $fields = [];
  $db_fields = [];
  $records = islandora_solr_get_fields($field_type, FALSE, FALSE);
  foreach ($records as $record) {
    $db_fields[] = $record['solr_field'];
  }
  if (!$form_state->getValues()) {
    // Get values from DB unfiltered, not simplified and incremental keys.
    // Set fields to populate the admin table.
    foreach ($records as $key => $record) {
      $fields[$key] = [
        'solr_field' => $record['solr_field'],
        'field_type' => $record['field_type'],
        'weight' => $record['weight'],
      ];
    }
    // Move solr_field_settings into the form state.
    foreach ($records as $key => $value) {
      $solr_field = $value['solr_field'];
      $solr_field_settings = $value['solr_field_settings'];
      $form_state->set(['solr_field_settings', 'islandora_solr_' . $field_type, $solr_field], $solr_field_settings);
    }
  }

  // 2. Triggered element (Add item or remove).
  if ($form_state->getTriggeringElement() &&
      $form_state->getTriggeringElement()['#field_type'] == $field_type) {
    // 2.a add field.
    if ($form_state->getTriggeringElement()['#name'] == $field_type . '_add_more') {
      // Get new solr field value.
      $add_solr_field = $form_state->getValue(['islandora_solr_' . $field_type, 'add_solr_field']);
      // Check if the value is empty.
      $solr_field_error = NULL;
      if (empty($add_solr_field)) {
        $solr_field_error = t('Field cannot be empty.');
      }
      // Check if value is already added.
      $check_terms = $form_state->getValue(['islandora_solr_' . $field_type, 'terms']);
      if ($check_terms) {
        foreach ($check_terms as $key => $term) {
          if (isset($term['solr_field']) && $term['solr_field'] == $add_solr_field) {
            $solr_field_error = t('Solr term can only be added once.');
          }
        }
      }
      // Add new field to values.
      if ($solr_field_error == NULL) {
        $form_state_terms = $form_state->getValue(['islandora_solr_' . $field_type, 'terms']);
        $form_state_terms[] = ['solr_field' => $add_solr_field];
        $form_state->setValue(['islandora_solr_' . $field_type, 'terms'], $form_state_terms);
      }
    }
    // 2.b. remove field.
    elseif ($form_state->getTriggeringElement()['#button_type'] == "{$field_type}_remove") {
      // Triggered field #.
      $field = $form_state->getTriggeringElement()['#field'];
      $solr_field = $form_state->getValue(['islandora_solr_' . $field_type, 'terms', $field, 'solr_field']);
      // Remove field.
      $form_state_terms = $form_state->getValue(['islandora_solr_' . $field_type, 'terms']);
      foreach ($form_state_terms as $key => $value) {
        if ($field == $value['solr_field']) {
          unset($form_state_terms[$key]);
        }
      }
      $form_state->setValue(['islandora_solr_' . $field_type, 'terms'], $form_state_terms);
      // Remove field values.
      $form_state->set(['solr_field_settings', 'islandora_solr_' . $field_type, $field], NULL);
    }
  }
  if ($form_state->getValue(['islandora_solr_' . $field_type, 'terms'])) {
    $fields = $form_state->getValue(['islandora_solr_' . $field_type, 'terms']);
  }

  // Render fields.
  $rows = [];
  if ($fields) {
    $count = count($fields);
    $fields = array_values($fields);
    foreach ($fields as $key => $value) {
      $row = [];
      $solr_field = $value['solr_field'];
      $row[] = [
        '#type' => 'item',
        '#markup' => $solr_field,
      ];
      $row['solr_field'] = [
        '#type' => 'hidden',
        '#value' => $solr_field,
      ];
      $solr_field_settings = NULL;
      if ($form_state->get(['solr_field_settings', 'islandora_solr_' . $field_type, $solr_field, 'label'])) {
        $solr_field_settings = $form_state->get(['solr_field_settings', 'islandora_solr_' . $field_type, $solr_field, 'label']);
      }
      $row[] = [
        '#markup' => $solr_field_settings ? t('Label: @label', ['@label' => $solr_field_settings]) : '',
      ];
      if (in_array($solr_field, $db_fields)) {
        $row[] = [
          '#type' => 'link',
          '#title' => t('configure'),
          '#href' => '#',
          '#options' => ['attributes' => ['class' => 'islandora-solr-configure-link']],
        ];
      }
      else {
        $row[] = [];
      }
      $row[] = [
        '#type' => 'button',
        '#value' => t('remove'),
        '#button_type' => "{$field_type}_remove",
        '#name' => "{$solr_field}_remove",
        '#field_type' => $field_type,
        '#field' => $solr_field,
        '#ajax' => [
          'callback' => '_islandora_solr_update_fields',
          'wrapper' => 'islandora-solr-' . $field_type_class . '-wrapper',
          'event' => 'click',
          'effect' => 'none',
        ],
      ];
      $row['weight'] = [
        '#type' => 'weight',
        '#default_value' => isset($value['weight']) ? $value['weight'] : $key,
        '#attributes' => ['class' => ['solr-weight-' . $field_type_class, "$field_type_class-table-order-weight"]],
      ];
      $row['#attributes']['class'][] = 'draggable';
      $rows[] = $row;
    }
  }
  // Create header array.
  $header = [
    [
      'data' => t('Solr field'),
      'colspan' => 2,
      'class' => 'islandora-solr-solr-field-header',
    ],
    ['data' => t('Settings')],
    ['data' => t('Operations'), 'colspan' => 2],
    ['data' => t('Weight')],
  ];
  // Render form.
  $terms['terms'] = [
    '#type' => 'table',
    '#empty' => t('No fields defined'),
    '#tree' => TRUE,
    '#header' => $header,
    '#attributes' => [
      'id' => "islandora-solr-$field_type_class",
      'class' => ['islandora-solr-fields-table'],
    ],
    '#tabledrag' => [
      [
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => "$field_type_class-table-order-weight",
      ],
    ],
  ];
  $terms['terms'] = array_merge($terms['terms'], $rows);
  $terms['add_solr_field'] = array(
    '#type' => 'textfield',
    '#title' => t('Add another item'),
    '#size' => 45,
    '#autocomplete_route_name' => 'islandora_solr.autocomplete_luke',
    '#default_value' => '',
  );
  if (isset($solr_field_error) && !empty($solr_field_error)) {
    $terms['add_solr_field']['#title'] .= '<br /><span class="solr-admin-error">' . $solr_field_error . '</span>';
    $terms['add_solr_field']['#attributes'] = ['class' => ['error']];
  }

  $terms['add_more'] = [
    '#type' => 'button',
    '#value' => t('Add'),
    '#attributes' => ['class' => ['islandora-solr-add-more-submit']],
    '#name' => $field_type . '_add_more',
    '#field_type' => $field_type,
    '#id' => 'edit-islandora-solr-' . $field_type_class . '-add-more',
    '#ajax' => [
      'callback' => '_islandora_solr_update_fields',
      'wrapper' => 'islandora-solr-' . $field_type_class . '-wrapper',
      'event' => 'click',
      'effect' => 'none',
    ],
  ];
}

/**
 * Checks for dismax.
 *
 * @param string $solr_url
 *   URL which point to Solr.
 * @param string $selected_handler
 *   Handler to check if dismax is allowed on it.
 *
 * @return bool
 *   TRUE if dismax is allowed, FALSE if not.
 *
 * @see islandora_solr_admin_settings()
 */
function _islandora_solr_check_dismax($solr_url, $selected_handler) {
  $xml = islandora_solr_get_solrconfig_xml($solr_url);

  $handlers = array();
  if ($xml) {
    if ($selected_handler) {
      $handlers = $xml->xpath("//requestHandler[@class='solr.SearchHandler' and @name='$selected_handler']");
    }
    else {
      $potential_handlers = $xml->xpath("//requestHandler[@class='solr.SearchHandler' and @default='true']");
      if ($potential_handlers) {
        // We don't have a handler name, but we do have a the default handler.
        $handlers = $potential_handlers;
      }
      else {
        $potential_handlers = $xml->xpath("//requestHandler[@class='solr.SearchHandler' and @name='/select']");
        if ($potential_handlers) {
          // We don't have a name or a declared, so look at the implied default.
          // Solr 3.6 seems to be able to store settings here which get applied
          // to all request handlers...
          // XXX: Might have to make this always get checked for completeness,
          // if it can really provide general defaults.
          $handlers = $potential_handlers;
        }
      }
    }
  }
  else {
    drupal_set_message(t('Error retrieving @file from Solr.', array('@file' => 'solrconfig.xml')), 'warning');
  }

  // An empty array evaluates to false... Let's coerce into a boolean.
  return TRUE == array_filter($handlers, function ($handler) {
    // XPath return an array of elements, and empty array evaluates to FALSE...
    // Coerce into a boolean, just because.
    return TRUE == $handler->xpath('lst/str[@name="qf"]');
  });
}

/**
 * Submit callback function for fields.
 *
 * Used by 'Add field', 'Configure', 'Remove'.
 *
 * @param array $form
 *   An associative array containing the form definition.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   An associative array containing the form state.
 */
function _islandora_solr_update_fields_submit($form, FormStateInterface $form_state) {
  $form_state->setRebuild();
}

/**
 * Ajax callback function for fields.
 *
 * Used by 'Add field', 'Remove' links/buttons.
 *
 * @param array $form
 *   An associative array containing the form definition.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   A form state.
 *
 * @return array
 *   The part of the form array that needs asynchronous updating.
 */
function _islandora_solr_update_fields($form, FormStateInterface $form_state) {

  // Check what section the call is from.
  switch ($form_state->getTriggeringElement()['#field_type']) {
    case 'result_fields':
      return $form['default_display_settings']['islandora_solr_result_fields'];

    case 'sort_fields':
      return $form['sort']['islandora_solr_sort_fields'];

    case 'facet_fields':
      return $form['facet_settings']['islandora_solr_facet_fields'];

    case 'search_fields':
      return $form['advanced_search_block']['islandora_solr_search_fields'];
  }
}

/**
 * Select the fields from the raw input values the modal form.
 *
 * AJAX call fun.
 *
 * @todo This should probably be removed and values we don't need maybe should
 *   be filtered out before sending it back to the main form.
 */
function _islandora_solr_handle_solr_field_settings($solr_field_settings = NULL, $field_type = NULL) {
  $to_return = FALSE;

  if (!empty($solr_field_settings) && !empty($field_type)) {
    switch ($field_type) {
      case 'result_fields':
        $to_return = array(
          'label' => trim($solr_field_settings['label']),
          'link_rendering' => $solr_field_settings['link_rendering'],
          'snippet' => isset($solr_field_settings['snippet']) ? $solr_field_settings['snippet'] : NULL,
          'date_format' => isset($solr_field_settings['date_format']) ? trim($solr_field_settings['date_format']) : '',
          'truncation_type' => isset($solr_field_settings['truncation_type']) ? trim($solr_field_settings['truncation_type']) : 'separate_value_option',
          'maximum_length' => isset($solr_field_settings['maximum_length']) ? trim($solr_field_settings['maximum_length']) : '',
          'add_ellipsis' => isset($solr_field_settings['add_ellipsis']) ? $solr_field_settings['add_ellipsis'] : FALSE,
          'wordsafe' => isset($solr_field_settings['wordsafe']) ? $solr_field_settings['wordsafe'] : FALSE,
          'wordsafe_length' => isset($solr_field_settings['wordsafe_length']) ? $solr_field_settings['wordsafe_length'] : 1,
          'permissions' => empty($solr_field_settings['enable_permissions']) ? FALSE : $solr_field_settings['permissions'],
        );
        break;

      case 'sort_fields':
        $to_return = array(
          'label' => trim($solr_field_settings['label']),
          'permissions' => empty($solr_field_settings['enable_permissions']) ? FALSE : $solr_field_settings['permissions'],
        );
        break;

      case 'facet_fields':
        $fields = array_combine(array(
          'label',
          'range_facet_select',
          'range_facet_variable_gap',
          'range_facet_start',
          'range_facet_end',
          'range_facet_gap',
          'date_facet_format',
          'range_facet_slider_enabled',
          'range_facet_slider_color',
          'date_filter_datepicker_enabled',
          'date_filter_datepicker_range',
          'pid_object_label',
          'boolean_facet_true_replacement',
          'boolean_facet_false_replacement',
          'sort_by',
        ), array(
          'label',
          'range_facet_select',
          'range_facet_variable_gap',
          'range_facet_start',
          'range_facet_end',
          'range_facet_gap',
          'date_facet_format',
          'range_facet_slider_enabled',
          'range_facet_slider_color',
          'date_filter_datepicker_enabled',
          'date_filter_datepicker_range',
          'pid_object_label',
          'boolean_facet_true_replacement',
          'boolean_facet_false_replacement',
          'sort_by',
        ));
        $relevant_values = array_intersect_key($solr_field_settings, $fields);
        $relevant_values['label'] = isset($relevant_values['label']) ?
          trim($relevant_values['label']) :
          '';
        $relevant_values['permissions'] = empty($solr_field_settings['enable_permissions']) ? FALSE : $solr_field_settings['permissions'];
        $to_return = $relevant_values;
        break;

      case 'search_fields':
        $to_return = array(
          'label' => trim($solr_field_settings['label']),
          'permissions' => empty($solr_field_settings['enable_permissions']) ? FALSE : $solr_field_settings['permissions'],
        );
        break;
    }
  }

  return $to_return;
}

/**
 * Function which returns the form function name associated with a field type.
 *
 * @param string $field_type
 *   Field type to get the form function name for.
 *
 * @return bool|string
 *   A form function name associated with the field type, or FALSE if field
 *   type is not given or does not exist.
 */
function _islandora_solr_admin_get_form_function($field_type = NULL) {
  if (!empty($field_type)) {
    switch ($field_type) {
      case 'result_fields':
        $form_callback = 'islandora_solr_admin_settings_result_fields';
        break;

      case 'sort_fields':
        $form_callback = 'islandora_solr_admin_settings_sort_fields';
        break;

      case 'facet_fields':
        $form_callback = 'islandora_solr_admin_settings_facet_fields';
        break;

      case 'search_fields':
        $form_callback = 'islandora_solr_admin_settings_search_fields';
        break;

      default:
        return FALSE;
    }
    return $form_callback;
  }
  return FALSE;
}

/**
 * @defgroup dialog-forms
 * @{
 * Form functions for the modal dialogs.
 */

/**
 * Genereate the element definition for the permission fieldset.
 *
 * @param bool|array $permissions
 *   The selected permissions (from the $form_state) either an array containing
 *   key values, or FALSE if it is not enabled.
 * @param array $permissions_default
 *   The permissions to select by default.
 * @param array $permissions_disable
 *   Some permissions which we will not allow to be changed (set as disabled in
 *   the form).
 * @param bool $default_value
 *   Whether the checkbox is to be checked by default.
 *
 * @return array
 *   An associative array containing the definition for the permissions
 *   fieldset.
 */
function islandora_solr_get_admin_permissions_fieldset($permissions, $permissions_default, $permissions_disable, $default_value) {
  if (is_array($permissions)) {
    $default_enable = !empty($permissions) ? TRUE : $default_value;
  }
  else {
    $default_enable = $permissions;
  }

  $permissions_fieldset = array(
    '#type' => 'fieldset',
    '#title' => t('Permissions'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    'enable_permissions' => array(
      '#type' => 'checkbox',
      '#title' => t('Enable permissions'),
      '#default_value' => $default_enable,
    ),
    'permissions' => array(
      '#type' => 'checkboxes',
      '#title' => t('Permissions'),
      '#options' => user_roles(),
      '#default_value' => !empty($permissions) ? $permissions : $permissions_default,
      '#description' => t('Select which roles can access this field.<br /><strong>Note:</strong> checkboxes may be grayed out for roles which do not have permission to search the Solr index.'),
      '#states' => array(
        'visible' => array(
          ':input[name="enable_permissions"]' => array('checked' => TRUE),
        ),
      ),
    ),
  );
  foreach ($permissions_disable as $rid) {
    $permissions_fieldset['permissions'][$rid] = array(
      '#disabled' => TRUE,
    );
  }

  return $permissions_fieldset;
}

/**
 * Form for result field settings.
 *
 * @params array $variables
 *   This parameter contains default values to be passed from the main form.
 */
function islandora_solr_admin_settings_result_fields($form, &$form_state, $variables) {
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
 * Utility function to append permissions and actions to the modal.
 *
 * @param array $values
 *   An array of values.
 * @param array $form
 *   An array representing the Drupal form, passed by reference.
 * @param bool $default_value
 *   Whether the default enabled checkbox is to be TRUE or FALSE. *
 */
function islandora_solr_append_permissions_and_actions($values, &$form, $default_value = TRUE) {
  $permissions = isset($values['permissions']) ? $values['permissions'] : array();
  $permissions_disable = _islandora_solr_permissions_disable();
  $permissions_default = _islandora_solr_permissions_default();
  $form['options']['permissions_fieldset'] = islandora_solr_get_admin_permissions_fieldset($permissions, $permissions_default, $permissions_disable, $default_value);

  $form['actions'] = array(
    '#type' => 'container',
    '#attributes' => array('class' => array('form-buttons')),
  );
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Apply'),
    '#weight' => 5,
    '#field' => 'dialog_submit',
    '#field_type' => 'result_fields',
    '#name' => 'result-fields-dialog-submit',
    '#submit' => array('_islandora_solr_update_fields_submit'),
    '#ajax' => array(
      'callback' => '_islandora_solr_admin_settings_dialog',
      'wrapper' => 'islandora-solr-admin-dialog-form',
      'effect' => 'none',
      'event' => 'click',
      'progress' => array('message' => '', 'type' => 'throbber'),
    ),
  );
}

/**
 * Form for sort field settings.
 *
 * @params array $variables
 *   This parameter contains default values to be passed from the main form.
 */
function islandora_solr_admin_settings_search_or_sort_fields($form, &$form_state, $variables) {
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
 * Form for facet field settings.
 *
 * @params array $variables
 *   This parameter contains default values to be passed from the main form.
 */
function islandora_solr_admin_settings_facet_fields($form, &$form_state, $variables) {
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
  $form['options']['sort_by'] = array(
    '#type' => 'radios',
    '#title' => t('Sort by'),
    '#default_value' => isset($values['sort_by']) ? $values['sort_by'] : 'count',
    '#options' => array(
      'count' => t('Count of facet (numerically)'),
      'index' => t('Text labels (alphabetically)'),
    ),
    '#description' => t('Facets can be sorted by text label or the count of the facet. If you sort by text labels AND replace PID with object label your sort order is not guaranteed.'),
  );

  if (islandora_solr_is_date_field($solr_field)) {
    // Add in defaults, to avoid isset() tests.
    $values += array(
      'range_facet_select' => 0,
      'range_facet_variable_gap' => 0,
      'range_facet_start' => 'NOW/YEAR-20YEARS',
      'range_facet_end' => 'NOW',
      'range_facet_gap' => '+1YEAR',
      'date_facet_format' => 'Y',
      'range_facet_slider_enabled' => 0,
      'range_facet_slider_color' => '#edc240',
      'date_filter_datepicker_enabled' => 0,
      'date_filter_datepicker_range' => '-100:+3',
    );
    $form['options']['date_facet_format'] = array(
      '#type' => 'textfield',
      '#title' => t('Date format'),
      '#default_value' => $values['date_facet_format'],
      '#description' => t('The format of the date, as it will be displayed in the facet block. Use <a href="@url">PHP date()</a> formatting. Works best when the date format matches the granularity of the source data. Otherwise it is possible that there will be duplicates displayed.', array('@url' => 'http://php.net/manual/function.date.php')),
    );

    $form['options']['range_facet'] = array(
      '#type' => 'fieldset',
      '#id' => 'range-facet-wrapper',
      '#collapsible' => FALSE,
      '#collapsed' => TRUE,
    );

    // @todo Grey out if LUKE says it's not possible to use as a range field?
    //   Add AJAX callback to show more options?
    $form['options']['range_facet']['range_facet_select'] = array(
      '#type' => 'checkbox',
      '#title' => t('Range facet'),
      '#default_value' => $values['range_facet_select'],
      '#description' => t('Toggles whether this facet field should be configured as a Solr range facet.'),
    );

    // @todo Check for non-ajax values.
    $form['options']['range_facet']['wrapper'] = array(
      '#type' => 'container',
      '#states' => array(
        'visible' => array(
          ':input[name="range_facet_select"]' => array('checked' => TRUE),
        ),
      ),
    );
    $form['options']['range_facet']['wrapper']['range_facet_variable_gap'] = array(
      '#type' => 'checkbox',
      '#title' => t('Variable range gap'),
      '#return_value' => 1,
      '#default_value' => $values['range_facet_variable_gap'],
      '#description' => t('When checked, the following date range settings will be used by default, but if a date range is filtered down, a new range gap will be calculated and applied. When left unchecked, the following settings provide fixed range gaps.'),
    );
    $form['options']['range_facet']['wrapper']['range_facet_start'] = array(
      '#type' => 'textfield',
      '#title' => t('Start'),
      '#default_value' => $values['range_facet_start'],
      '#description' => t('The lower bound of the first date range for all date faceting on this field. This should be a single date expression which may use the <a href="@url">DateMathParser</a> syntax.', array('@url' => 'http://lucene.apache.org/solr/api/org/apache/solr/util/DateMathParser.html')),
    );
    $form['options']['range_facet']['wrapper']['range_facet_end'] = array(
      '#type' => 'textfield',
      '#title' => t('End'),
      '#default_value' => $values['range_facet_end'],
      '#description' => t('The minimum upper bound of the last date range for all Date Faceting on this field. This should be a single date expression which may use the <a href="@url">DateMathParser</a> syntax.', array('@url' => 'http://lucene.apache.org/solr/api/org/apache/solr/util/DateMathParser.html')),
    );
    $form['options']['range_facet']['wrapper']['range_facet_gap'] = array(
      '#type' => 'textfield',
      '#title' => t('Gap'),
      '#default_value' => $values['range_facet_gap'],
      '#description' => t('The size of each date range, expressed as an interval to be added to the lower bound using the <a href="@url">DateMathParser</a> syntax.', array('@url' => 'http://lucene.apache.org/solr/api/org/apache/solr/util/DateMathParser.html')),
    );
    // Range slider.
    $form['options']['range_facet']['wrapper']['range_slider'] = array(
      '#type' => 'fieldset',
      '#title' => t('Range slider'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );
    $form['options']['range_facet']['wrapper']['range_slider']['range_facet_slider_enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable range slider'),
      '#return_value' => 1,
      '#default_value' => $values['range_facet_slider_enabled'],
      '#description' => t('When checked, the normal range facet will be replaced by a range slider widget.'),
    );
    $form['options']['range_facet']['wrapper']['range_slider']['range_facet_slider_color'] = array(
      '#type' => 'textfield',
      '#title' => t('Slider color'),
      '#description' => t('The range slider\'s color, formatted as a hex value. Defaults to <span style="color: #edc240">#edc240</span>'),
      '#default_value' => $values['range_facet_slider_color'],
      '#states' => array(
        'visible' => array(
          ':input[name="range_facet_slider_enabled"]' => array('checked' => TRUE),
        ),
      ),
    );

    // Date filter.
    $form['options']['range_facet']['wrapper']['date_filter'] = array(
      '#type' => 'fieldset',
      '#title' => t('Date range filter'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );
    $form['options']['range_facet']['wrapper']['date_filter']['date_filter_datepicker_enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable date range filter'),
      '#return_value' => 1,
      '#default_value' => $values['date_filter_datepicker_enabled'],
      '#description' => t('When checked, a date range filter will become available underneath the date range facet. The date range filter includes <em>from date</em> and a <em>to date</em> text fields. It also comes with a calendar popup widget.'),
    );
    $form['options']['range_facet']['wrapper']['date_filter']['date_filter_datepicker_range'] = array(
      '#type' => 'textfield',
      '#title' => t('Years back and forward'),
      '#default_value' => $values['date_filter_datepicker_range'],
      '#size' => 10,
      '#maxsize' => 10,
      '#description' => t('The range of years displayed in the year drop-down menu. These are either relative to today\'s year ("-nn:+nn"), to the currently selected year ("c-nn:c+nn"), an absolute ("nnnn:nnnn"), or combinations of these formats ("nnnn:-nn"). For more info, check the jQuery UI <a href="@url" target="_blank">datepicker documentation</a>.', array('@url' => 'http://api.jqueryui.com/datepicker/#option-yearRange')),
      '#states' => array(
        'visible' => array(
          ':input[name="date_filter_datepicker_enabled"]' => array('checked' => TRUE),
        ),
      ),
    );
  }
  elseif (islandora_solr_is_boolean_field($solr_field)) {
    // Defaults.
    $values += array(
      'boolean_facet_true_replacement' => '',
      'boolean_facet_false_replacement' => '',
    );
    $form['options']['boolean_facet'] = array(
      '#type' => 'fieldset',
      '#title' => t('Boolean Facet Options'),
      '#collapsible' => FALSE,
      'blurb' => array(
        '#markup' => t('Enter replacement values for the labels of TRUE and FALSE results for boolean facets. Leaving a field empty will cause that result to show up as "true" or "false", respectively, which may be less than clear to the end user.'),
      ),
      'boolean_facet_true_replacement' => array(
        '#type' => 'textfield',
        '#title' => t('Replacement for TRUE values'),
        '#default_value' => $values['boolean_facet_true_replacement'],
      ),
      'boolean_facet_false_replacement' => array(
        '#type' => 'textfield',
        '#title' => t('Replacement for FALSE values'),
        '#default_value' => $values['boolean_facet_false_replacement'],
      ),
    );
  }

  // Permissions.
  islandora_solr_append_permissions_and_actions($values, $form);

  $form['options']['pid_object_label'] = array(
    '#type' => 'checkbox',
    '#title' => t('Replace PID with Object Label'),
    '#return_value' => 1,
    '#default_value' => (isset($values['pid_object_label']) ? $values['pid_object_label'] : 0),
    '#description' => t("Replace a PID (islandora:foo) or a URI (info:fedora/islandora:foo) with that object's label. Will only work with non-tokenized Solr fields (full literal strings)."),
  );

  return $form;
}

/**
 * Returns an array of role IDs to disable checkboxes.
 */
function _islandora_solr_permissions_disable() {
  $user_roles = array_keys(user_roles());
  $permissions_solr = array_keys(user_roles(FALSE, 'search islandora solr'));
  $permissions_disable = array_diff($user_roles, $permissions_solr);
  // If authenticated users have permission exclude all authenticated users
  // fields from the disable list.
  if (in_array('2', $permissions_solr)) {
    foreach ($permissions_disable as $key => $rid) {
      if ($rid != '1') {
        unset($permissions_disable[$key]);
      }
    }
  }
  return $permissions_disable;
}

/**
 * Returns an array of role id's to set default values for checkboxes.
 */
function _islandora_solr_permissions_default() {
  $user_roles = array_keys(user_roles());
  $permissions_solr = array_keys(user_roles(FALSE, 'search islandora solr'));
  $permissions_default = array_intersect($user_roles, $permissions_solr);
  // If authenticated users have permission include all authenticated users
  // fields to the default list.
  if (in_array('2', $permissions_solr)) {
    foreach ($user_roles as $rid) {
      if ($rid != '1' && !in_array($rid, $permissions_default)) {
        $permissions_default[] = $rid;
      }
    }
  }
  return $permissions_default;
}

/**
 * @} End of "defgroup dialog-forms".
 */
