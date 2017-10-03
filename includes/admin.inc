<?php

/**
 * @file
 * Contains all administration related functionality.
 */

/**
 * Generates fields for the admin fields table.
 *
 * First collects values to populate the table, then render the fields.
 *
 * @param array $form_state
 *   The form_state array of the form. Includes submitted values.
 * @param array $terms
 *   Terms array passed by reference. A base form element for the table is
 *   already created and new form elements are added in this function.
 * @param string $field_type
 *   An identifier for the field table which is being created.
 *
 * @link http://drupal.stackexchange.com/questions/14855/how-do-i-dynamically-fill-a-textfield-with-ajax/16576#16576 Stackexchange Post @endlink
 */
function islandora_solr_admin_settings_fields(&$form_state, &$terms, $field_type) {

  // Set variables.
  $field_type_class = str_replace('_', '-', $field_type);

  // Ways to populate the fields table:
  // 1. Saved values from the DB
  // 2. Triggered element
  // 2.a. Add field
  // 2.b. Remove field
  // 2.c. Configure field:
  // - Prepare array to populate dialog.
  // 2.d. Dialog submit:
  // - Process values from modal dialog: add variables to form_state array and
  //   update the table info.
  // 1. Saved values from the DB.
  if ($form_state['rebuild'] == FALSE) {

    // Get values from DB unfiltered, not simplified and incremental keys.
    $records = islandora_solr_get_fields($field_type, FALSE, FALSE);

    // Set fields to populate the admin table.
    $fields = array();
    foreach ($records as $key => $record) {
      $fields[$key] = array(
        'solr_field' => $record['solr_field'],
        'field_type' => $record['field_type'],
        'weight' => $record['weight'],
      );
    }
    // Move solr_field_settings into the form state.
    foreach ($records as $key => $value) {
      $solr_field = $value['solr_field'];
      $solr_field_settings = $value['solr_field_settings'];
      $form_state['solr_field_settings']['islandora_solr_' . $field_type][$solr_field] = $solr_field_settings;
    }
  }

  // 2. Triggered element (Add item or remove).
  if (isset($form_state['triggering_element']) &&
      $form_state['triggering_element']['#field_type'] == $field_type) {
    // Unset terms from input array.
    $form_state['values']['islandora_solr_' . $field_type]['terms'] = isset($form_state['input']['islandora_solr_' . $field_type]['terms']) ? $form_state['input']['islandora_solr_' . $field_type]['terms'] : array();
    unset($form_state['input']['islandora_solr_' . $field_type]);

    // 2.a add field.
    if ($form_state['triggering_element']['#name'] == $field_type . '_add_more') {
      // Get new solr field value.
      $add_solr_field = $form_state['values']['islandora_solr_' . $field_type]['add_solr_field'];
      // Check if the value is empty.
      $solr_field_error = NULL;
      if (empty($add_solr_field)) {
        $solr_field_error = t('Field cannot be empty.');
      }
      // Check if value is already added.
      $check_terms = $form_state['values']['islandora_solr_' . $field_type]['terms'];

      foreach ($check_terms as $key => $term) {
        if (isset($term['solr_field']) && $term['solr_field'] == $add_solr_field) {
          $solr_field_error = t('Solr term can only be added once.');
        }
      }
      // Add new field to values.
      if ($solr_field_error == NULL) {
        $form_state['values']['islandora_solr_' . $field_type]['terms'][] = array('solr_field' => $add_solr_field);
      }
    }
    // 2.b. remove field.
    if ($form_state['triggering_element']['#value'] == t('Remove')) {
      // Triggered field #.
      $field = $form_state['triggering_element']['#field'];
      $solr_field = $form_state['values']['islandora_solr_' . $field_type]['terms'][$field]['solr_field'];
      // Remove field.
      array_splice($form_state['values']['islandora_solr_' . $field_type]['terms'], $field, 1);
      // Remove field values.
      unset($form_state['solr_field_settings']['islandora_solr_' . $field_type][$solr_field]);
    }
    // 2.c. configure field.
    if ($form_state['triggering_element']['#value'] == t('Configure')) {
      // Triggered field #.
      $field = $form_state['triggering_element']['#field'];
      // Get field + dialog values and merge.
      $values = $form_state['values']['islandora_solr_' . $field_type]['terms'][$field];
      $solr_field = $form_state['values']['islandora_solr_' . $field_type]['terms'][$field]['solr_field'];
      $solr_field_settings = (array) \Drupal\Component\Utility\NestedArray::getValue($form_state, array(
        'solr_field_settings',
        'islandora_solr_' . $field_type,
        $solr_field,
      ));
      $values = array_merge_recursive($values, $solr_field_settings);
      // Prepare dialog values.
      $dialog_id = 'edit-islandora-solr-' . $field_type_class . '-terms-' . $field . '-dialog-button';
      $form_state['dialog'] = array(
        'solr_field' => $solr_field,
        'field_type' => $field_type,
        'dialog_id' => $dialog_id,
        'values' => $values,
      );
    }
    // 2.d. Dialog submit.
    if ($form_state['triggering_element']['#value'] == t('Dialog')) {
      // Triggered field #.
      $field = $form_state['triggering_element']['#field'];
      // Get $solr_field.
      $solr_field = $form_state['values']['islandora_solr_' . $field_type]['terms'][$field]['solr_field'];
      // Get dialog values from recent dialog submit.
      $dialog_values = $form_state['input']['_dialog_values'] = json_decode($form_state['input']['_dialog_values'], TRUE);
      // Save the dialog values in the form state.
      $form_state['solr_field_settings']['islandora_solr_' . $field_type][$solr_field] = _islandora_solr_handle_solr_field_settings($dialog_values, $field_type);
    }
  }

  // Prepare fields array.
  if (isset($form_state['values']['islandora_solr_' . $field_type]['terms'])) {
    $fields = $form_state['values']['islandora_solr_' . $field_type]['terms'];
  }

  // If no fields available set string.
  if (empty($fields)) {
    $terms['no_fields'] = array(
      '#markup' => t('No fields defined'),
    );
  }
  // Else render fields.
  else {
    $term = array();
    $count = count($fields);
    $fields = array_values($fields);
    foreach ($fields as $key => $value) {
      $solr_field = $value['solr_field'];

      $term['draggable_handler'] = array(
        '#type' => 'item',
        '#markup' => '',
      );
      $term['solr_field_render'] = array(
        '#type' => 'item',
        '#markup' => isset($value['solr_field']) ? $value['solr_field'] : '',
      );
      $term['solr_field'] = array(
        '#type' => 'hidden',
        '#value' => isset($value['solr_field']) ? $value['solr_field'] : '',
      );
      $solr_field_settings = NULL;
      if (isset($form_state['solr_field_settings']['islandora_solr_' . $field_type][$solr_field]['label'])) {
        $solr_field_settings = function_exists('i18n_string') ?
          i18n_string("islandora_solr:field_settings:$field_type:label:$solr_field", $form_state['solr_field_settings']['islandora_solr_' . $field_type][$solr_field]['label']) :
          $form_state['solr_field_settings']['islandora_solr_' . $field_type][$solr_field]['label'];
      }
      $term['solr_field_settings'] = array(
        '#markup' => $solr_field_settings ? t('Label: @label', array('@label' => $solr_field_settings)) : '',
      );
      $term['weight'] = array(
        '#type' => 'weight',
        '#default_value' => isset($value['weight']) ? $value['weight'] : $key,
        '#delta' => $count >= 10 ? $count : 10,
        '#attributes' => array('class' => array('solr-weight-' . $field_type_class)),
      );
      $term['configure'] = array(
        '#type' => 'link',
        '#title' => t('configure'),
        '#href' => '#',
        '#options' => array('attributes' => array('class' => 'islandora-solr-configure-link')),
      );
      $term['configure_button'] = array(
        '#type' => 'submit',
        '#value' => t('Configure'),
        '#field' => $key,
        '#field_type' => $field_type,
        '#attributes' => array('class' => array('islandora-solr-configure-submit')),
        '#submit' => array('_islandora_solr_update_fields_submit'),
        '#name' => $field_type . '_configure_' . $key,
        '#id' => 'edit-islandora-solr-' . $field_type_class . '-terms-' . $key . '-configure-button',
        '#ajax' => array(
          'callback' => '_islandora_solr_admin_settings_field_configure',
          'wrapper' => 'islandora-solr-' . $field_type_class . '-wrapper',
          'effect' => 'none',
          'event' => 'click',
          'progress' => array('message' => '', 'type' => 'throbber'),
        ),
      );
      $term['remove'] = array(
        '#type' => 'link',
        '#title' => t('remove'),
        '#href' => '#',
        '#options' => array('attributes' => array('class' => 'islandora-solr-remove-link')),
      );
      $term['remove_button'] = array(
        '#type' => 'submit',
        '#value' => t('Remove'),
        '#field' => $key,
        '#field_type' => $field_type,
        '#attributes' => array('class' => array('islandora-solr-remove-submit')),
        '#submit' => array('_islandora_solr_update_fields_submit'),
        '#name' => $field_type . '_remove_' . $key,
        '#id' => 'edit-islandora-solr-' . $field_type_class . '-terms-' . $key . '-remove-button',
        '#ajax' => array(
          'callback' => '_islandora_solr_update_fields',
          'wrapper' => 'islandora-solr-' . $field_type_class . '-wrapper',
          'effect' => 'none',
          'event' => 'click',
          'progress' => array('message' => '', 'type' => 'throbber'),
        ),
      );
      $term['dialog_button'] = array(
        '#type' => 'submit',
        '#value' => t('Dialog'),
        '#field' => $key,
        '#field_type' => $field_type,
        '#attributes' => array('class' => array('islandora-solr-dialog-submit')),
        '#submit' => array('_islandora_solr_update_fields_submit'),
        '#name' => $field_type . '_dialog_' . $key,
        '#id' => 'edit-islandora-solr-' . $field_type_class . '-terms-' . $key . '-dialog-button',
        '#ajax' => array(
          'callback' => '_islandora_solr_update_fields',
          'wrapper' => 'islandora-solr-' . $field_type_class . '-wrapper',
          'effect' => 'none',
          'event' => 'click',
          'progress' => array('message' => '', 'type' => 'throbber'),
        ),
      );
      $terms['terms'][] = $term;
    }
  }
  $terms['add_solr_field'] = array(
    '#type' => 'textfield',
    '#title' => t('Add another item'),
    '#size' => 45,
    '#autocomplete_path' => 'islandora_solr/autocomplete_luke',
    '#default_value' => '',
  );
  if (isset($solr_field_error) && !empty($solr_field_error)) {
    $terms['add_solr_field']['#title'] .= '<br /><span class="solr-admin-error">' . $solr_field_error . '</span>';
    $terms['add_solr_field']['#attributes'] = array('class' => array('error'));
  }

  $terms['add_more'] = array(
    '#type' => 'button',
    '#value' => t('Add'),
    '#attributes' => array('class' => array('islandora-solr-add-more-submit')),
    '#submit' => array('_islandora_solr_update_fields_submit'),
    '#name' => $field_type . '_add_more',
    '#field_type' => $field_type,
    '#id' => 'edit-islandora-solr-' . $field_type_class . '-add-more',
    '#ajax' => array(
      'callback' => '_islandora_solr_update_fields',
      'wrapper' => 'islandora-solr-' . $field_type_class . '-wrapper',
      'event' => 'click',
      'effect' => 'none',
    ),
  );
}

/**
 * Theme function to create an admin table for result fields.
 *
 * @param array $variables
 *   Array containing form elements to be themed.
 *
 * @return string
 *   Rendered table.
 */
function theme_islandora_solr_admin_fields($variables) {
  $form = $variables['form'];

  $field_type = $form['#field_type'];

  // Render class name from field type.
  $field_type_class = str_replace('_', '-', $field_type);

  $rows = array();

  // If no_fields is set, we don't render all the fields.
  if (isset($form['no_fields'])) {
    $row = array();
    $row[] = array('data' => \Drupal::service("renderer")->render($form['no_fields']), 'colspan' => 10);

    // Add to rows.
    $rows[] = array('data' => $row);
  }
  else {
    foreach ($form['terms'] as $key => $element) {
      if (is_array($element) && \Drupal\Core\Render\Element::child($key)) {
        $row = array();
        $row[] = array('data' => \Drupal::service("renderer")->render($form['terms'][$key]['draggable_handler']), 'class' => 'islandora-solr-multiple-drag');
        $row[] = array('data' => \Drupal::service("renderer")->render($form['terms'][$key]['solr_field_render']), 'class' => 'islandora-solr-solr-field-render');
        $row[] = array('data' => \Drupal::service("renderer")->render($form['terms'][$key]['solr_field_settings']), 'class' => 'islandora-solr-solr-field-settings');
        $row[] = array('data' => \Drupal::service("renderer")->render($form['terms'][$key]['weight']));
        $row[] = array('data' => \Drupal::service("renderer")->render($form['terms'][$key]['configure']), 'class' => 'islandora-solr-operations');
        $row[] = array('data' => \Drupal::service("renderer")->render($form['terms'][$key]['configure_button']), 'class' => 'islandora-solr-operations islandora-solr-operations-hidden');
        $row[] = array('data' => \Drupal::service("renderer")->render($form['terms'][$key]['remove']), 'class' => 'islandora-solr-operations');
        $row[] = array('data' => \Drupal::service("renderer")->render($form['terms'][$key]['remove_button']), 'class' => 'islandora-solr-operations islandora-solr-operations-hidden');
        $row[] = array('data' => \Drupal::service("renderer")->render($form['terms'][$key]['dialog_button']), 'class' => 'islandora-solr-operations islandora-solr-operations-hidden');

        // Add to rows.
        $rows[] = array('data' => $row, 'class' => array('draggable'));
      }
    }
  }

  // Create header array.
  $header = array();
  $header[] = array(
    'data' => t('Solr field'),
    'colspan' => 2,
    'class' => 'islandora-solr-solr-field-header',
  );
  $header[] = array('data' => t('Settings'));
  $header[] = array('data' => t('Weight'), 'class' => 'islandora-solr-solr-field-weight');
  $header[] = array('data' => t('Operations'), 'colspan' => 4);

  // Controls table.
  $rows_controls = array();
  // Add another item form elements.
  $rows_controls[] = array(
    'data' => array(
      array(
        'data' => \Drupal::service("renderer")->render($form['add_solr_field']) . \Drupal::service("renderer")->render($form['add_more']),
        'colspan' => 10,
      ),
    ),
    'no_striping' => 1,
    'class' => array('add-item-controls'),
  );

  // Render form.
  $output = '';
  // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// $output .= theme('table', array(
//     'header' => $header,
//     'rows' => $rows,
//     'attributes' => array(
//       'id' => 'islandora-solr-' . $field_type_class,
//       'class' => array('islandora-solr-fields-table'),
//     ),
//   ));

  // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// $output .= theme('table', array(
//     'header' => array(),
//     'rows' => $rows_controls,
//     'attributes' => array('class' => array('islandora-solr-table-controls')),
//   ));

  $output .= drupal_render_children($form);
  // Add tabledrag features.
  // @FIXME
// TableDrag is now attached with the #tabledrag property of certain render
// arrays. drupal_add_tabledrag() is now internal and should never be called directly.
//
//
// @see https://www.drupal.org/node/2160571
// drupal_add_tabledrag('islandora-solr-' . $field_type_class, 'order', 'sibling', 'solr-weight-' . $field_type_class);


  return $output;
}

/**
 * Submit button callback to update the Solr URL only.
 *
 * @param array $form
 *   An array containing the form structure.
 * @param array $form_state
 *   An array containing the form state.
 *
 * @see islandora_solr_admin_settings()
 */
function _islandora_solr_admin_refresh($form, &$form_state) {
  unset($form_state['submit_handlers']);
  \Drupal::formBuilder()->executeSubmitHandlers($form, $form_state);
  $form_state['rebuild'] = TRUE;
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
 * Admin autocomplete callback which returns solr fields from Luke.
 *
 * @param string $string
 *   String filled out in the autocomplete textfield.
 *
 * @return json
 *   A json array containing the Solr luke values that contain the given string.
 */
function _islandora_solr_autocomplete_luke($string = '') {

  $luke = islandora_solr_get_luke();
  $result = array();
  foreach ($luke['fields'] as $term => $value) {
    if (stripos($term, $string) !== FALSE) {
      // Search case insensitive, but keep the case on replace.
      $term_str = preg_replace("/$string/i", "<strong>\$0</strong>", $term);

      // Add strong elements to highlight the found string.
      $result[$term] = $term_str . '<strong style="position: absolute; right: 5px;">(' . $value['type'] . ')</strong>';
    }
  }
  // Sort alphabetically.
  ksort($result);

  drupal_json_output($result);
  exit();
}

/**
 * Submit callback function for fields.
 *
 * Used by 'Add field', 'Configure', 'Remove' and 'Dialog'.
 *
 * @param array $form
 *   An associative array containing the form definition.
 * @param array $form_state
 *   An associative array containing the form state.
 */
function _islandora_solr_update_fields_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
}

/**
 * Ajax callback function for fields.
 *
 * Used by 'Add field', 'Remove' and 'Dialog' links/buttons.
 *
 * @param array $form
 *   An associative array containing the form definition.
 * @param array $form_state
 *   An associative array containing the form state.
 *
 * @return array
 *   The part of the form array that needs asynchronous updating.
 */
function _islandora_solr_update_fields($form, &$form_state) {

  // Check what section the call is from.
  switch ($form_state['triggering_element']['#field_type']) {
    case 'result_fields':
      return $form['islandora_solr_tabs']['default_display_settings']['islandora_solr_result_fields'];

    case 'sort_fields':
      return $form['islandora_solr_tabs']['sort']['islandora_solr_sort_fields'];

    case 'facet_fields':
      return $form['islandora_solr_tabs']['facet_settings']['islandora_solr_facet_fields'];

    case 'search_fields':
      return $form['islandora_solr_tabs']['advanced_search_block']['islandora_solr_search_fields'];
  }
}

/**
 * AJAX callback function for 'Configure' link/button.
 *
 * Passes values from the field, loads the right form and populates and
 * triggers the modal dialog.
 *
 * @param array $form
 *   An associative array containing the form definition.
 * @param array $form_state
 *   An associative array containing the form state.
 *
 * @return array
 *   An array of AJAX commands.
 */
function _islandora_solr_admin_settings_field_configure($form, &$form_state) {
  $variables = $form_state['dialog'];
  $field_type = $form_state['dialog']['field_type'];
  $solr_field = $form_state['dialog']['solr_field'];
  $form_function = _islandora_solr_admin_get_form_function($field_type);

  $commands = array();

  // Open dialog.
  $commands[] = ajax_command_invoke('#islandora-solr-admin-dialog', 'dialog', array('open'));
  // Dialog title.
  $title = t('Configure field:') . ' ' . $solr_field;
  $commands[] = ajax_command_invoke('#islandora-solr-admin-dialog-title', 'html', array($title));
  // Dialog body.
  $form = \Drupal::formBuilder()->getForm($form_function, $variables);
  $body = \Drupal::service("renderer")->render($form);
  $commands[] = ajax_command_invoke('#islandora-solr-admin-dialog-body', 'html', array($body));
  // Attach behaviors (re-attaches javascript).
  $commands[] = ajax_command_invoke(NULL, 'islandoraSolrAttachBehaviors', array());

  return array('#type' => 'ajax', '#commands' => $commands);
}

/**
 * AJAX callback function for the modal dialog submit button.
 *
 * Closes the modal dialog and passes the values to the main form.
 *
 * @param array $form
 *   An associative array containing the form definition.
 * @param array $form_state
 *   An associative array containing the form state.
 *
 * @return array
 *   An array of AJAX commands.
 */
function _islandora_solr_admin_settings_dialog($form, &$form_state) {

  $dialog_id = $form_state['dialog']['dialog_id'];

  $commands = array();
  // Close dialog.
  $commands[] = ajax_command_invoke('#islandora-solr-admin-dialog', 'dialog', array('close'));

  // Call function which attaches the dialog form values to
  // Drupal.ajax.{dialog-button-id}.options.data.
  // After triggering the dialog ajax callback, the dialog values will be added
  // to the $form_state of the main form.
  $data = array(
    'id' => $dialog_id,
    'values' => $form_state['input'],
  );
  $commands[] = ajax_command_invoke(NULL, 'islandoraSolrDialogValues', array($data));
  // Trigger dialog button.
  $commands[] = ajax_command_invoke('#' . $dialog_id, 'trigger', array('click'));

  return array('#type' => 'ajax', '#commands' => $commands);
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
      '#description' => t('The format of the date, as it will be displayed in the search results. Use <a href="!url" target="_blank">PHP date()</a> formatting. Works best when the date format matches the granularity of the source data. Otherwise it is possible that there will be duplicates displayed.', array('!url' => 'http://php.net/manual/function.date.php')),
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
      '#description' => t('If selected attempt to truncate on a word boundary. See <a href="!url" target="_blank".>documentation</a> for more information.', array('!url' => 'https://api.drupal.org/api/drupal/includes!unicode.inc/function/truncate_utf8/7')),
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
      '#description' => t('The format of the date, as it will be displayed in the facet block. Use <a href="!url">PHP date()</a> formatting. Works best when the date format matches the granularity of the source data. Otherwise it is possible that there will be duplicates displayed.', array('!url' => 'http://php.net/manual/function.date.php')),
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
      '#description' => t('The lower bound of the first date range for all date faceting on this field. This should be a single date expression which may use the <a href="!url">DateMathParser</a> syntax.', array('!url' => 'http://lucene.apache.org/solr/api/org/apache/solr/util/DateMathParser.html')),
    );
    $form['options']['range_facet']['wrapper']['range_facet_end'] = array(
      '#type' => 'textfield',
      '#title' => t('End'),
      '#default_value' => $values['range_facet_end'],
      '#description' => t('The minimum upper bound of the last date range for all Date Faceting on this field. This should be a single date expression which may use the <a href="!url">DateMathParser</a> syntax.', array('!url' => 'http://lucene.apache.org/solr/api/org/apache/solr/util/DateMathParser.html')),
    );
    $form['options']['range_facet']['wrapper']['range_facet_gap'] = array(
      '#type' => 'textfield',
      '#title' => t('Gap'),
      '#default_value' => $values['range_facet_gap'],
      '#description' => t('The size of each date range, expressed as an interval to be added to the lower bound using the <a href="!url">DateMathParser</a> syntax.', array('!url' => 'http://lucene.apache.org/solr/api/org/apache/solr/util/DateMathParser.html')),
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
      '#description' => t('The range of years displayed in the year drop-down menu. These are either relative to today\'s year ("-nn:+nn"), to the currently selected year ("c-nn:c+nn"), an absolute ("nnnn:nnnn"), or combinations of these formats ("nnnn:-nn"). For more info, check the jQuery UI <a href="!url" target="_blank">datepicker documentation</a>.', array('!url' => 'http://api.jqueryui.com/datepicker/#option-yearRange')),
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
