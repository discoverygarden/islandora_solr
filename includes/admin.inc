<?php

/**
 * @file
 * Contains administration related helpers.
 */

use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
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
        if ($field_type == 'result_fields') {
          $url = Url::fromRoute('islandora_solr.configure_result_field', ['solr_field' => $solr_field]);
        }
        elseif ($field_type == 'sort_fields' || $field_type == 'search_fields') {
          $url = Url::fromRoute('islandora_solr.configure_sort_or_search_field', ['solr_field' => $solr_field, 'field_type' => $field_type]);
        }
        elseif ($field_type == 'facet_fields') {
          $url = Url::fromRoute('islandora_solr.configure_facet_field', ['solr_field' => $solr_field]);
        }
        $row[] = [
          '#title' => t('configure'),
          '#type' => 'link',
          '#url' => $url,
          '#attributes' => [
            'class' => [
              'button',
              'islandora-solr-configure-link',
              'use-ajax',
            ],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => Json::encode([
              'width' => 1000,
            ]),
          ],
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
          'wrapper' => "islandora-solr-{$field_type_class}-wrapper",
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
  $terms['add_solr_field'] = [
    '#type' => 'textfield',
    '#title' => t('Add another item'),
    '#size' => 45,
    '#autocomplete_route_name' => 'islandora_solr.autocomplete_luke',
    '#default_value' => '',
  ];
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

  $handlers = [];
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
    drupal_set_message(t('Error retrieving @file from Solr.', ['@file' => 'solrconfig.xml']), 'warning');
  }

  // An empty array evaluates to false... Let's coerce into a boolean.
  return TRUE == array_filter($handlers, function ($handler) {
    // XPath return an array of elements, and empty array evaluates to FALSE...
    // Coerce into a boolean, just because.
    return TRUE == $handler->xpath('lst/str[@name="qf"]');
  });
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

  $permissions_fieldset = [
    '#type' => 'fieldset',
    '#title' => t('Permissions'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    'enable_permissions' => [
      '#type' => 'checkbox',
      '#title' => t('Enable permissions'),
      '#default_value' => $default_enable,
    ],
    'permissions' => [
      '#type' => 'checkboxes',
      '#title' => t('Permissions'),
      '#options' => array_map(function($item) {
        return $item->label();
      }, user_roles()),
      '#default_value' => !empty($permissions) ? $permissions : $permissions_default,
      '#description' => t('Select which roles can access this field.<br /><strong>Note:</strong> checkboxes may be grayed out for roles which do not have permission to search the Solr index.'),
      '#states' => [
        'visible' => [
          ':input[name="enable_permissions"]' => ['checked' => TRUE],
        ],
      ],
    ],
  ];
  foreach ($permissions_disable as $rid) {
    $permissions_fieldset['permissions'][$rid] = [
      '#disabled' => TRUE,
    ];
  }

  return $permissions_fieldset;
}

/**
 * Utility function to append permissions and actions to the modal.
 *
 * @param array $values
 *   An array of values.
 * @param array $form
 *   An array representing the Drupal form, passed by reference.
 * @param bool $default_value
 *   Whether the default enabled checkbox is to be TRUE or FALSE.
 * @param callback $callback
 *   The callback for the modal ajax.
 */
function islandora_solr_append_permissions_and_actions($values, &$form, $default_value = TRUE, $callback = NULL) {
  $permissions = isset($values['permissions']) ? $values['permissions'] : [];
  $permissions_disable = _islandora_solr_permissions_disable();
  $permissions_default = _islandora_solr_permissions_default();
  $form['options']['permissions_fieldset'] = islandora_solr_get_admin_permissions_fieldset($permissions, $permissions_default, $permissions_disable, $default_value);

  $form['actions'] = [
    '#type' => 'container',
    '#attributes' => ['class' => ['form-buttons']],
  ];
  $form['actions']['submit'] = [
    '#type' => 'submit',
    '#value' => t('Apply'),
    '#weight' => 5,
    '#field' => 'dialog_submit',
    '#field_type' => 'result_fields',
    '#name' => 'result-fields-dialog-submit',
    '#attributes' => [
      'class' => [
        'use-ajax',
      ],
    ],
    '#ajax' => [
      'callback' => $callback,
      'event' => 'click',
    ],
  ];
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
