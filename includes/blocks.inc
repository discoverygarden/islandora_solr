<?php

/**
 * @file
 * Contains some block related functionality.
 */

/**
 * Implements hook_form_FORM_ID_alter().
 *
 * Currently checks for delta of explore and modifies the form to add custom
 * configuration options to setup facet display filters to be rendered.
 */
function islandora_solr_form_BLOCK_ADMIN_CONFIGURE_alter(&$form, &$form_state) {
  if ($form['module']['#value'] == 'islandora_solr' && $form['delta']['#value'] == 'explore') {
    // Get the variables for the form display facets.
    // @FIXME
// Could not extract the default value because it is either indeterminate, or
// not scalar. You'll need to provide a default value in
// config/install/islandora_solr.settings.yml and config/schema/islandora_solr.schema.yml.
$expore_config = (isset($form_state['islandora_solr_facet_filters']) ? $form_state['islandora_solr_facet_filters'] : \Drupal::config('islandora_solr.settings')->get('islandora_solr_explore_config'));

    // Check if remove was clicked and removed the label and filter from the
    // values and the table.
    if (isset($form_state['triggering_element']) && $form_state['triggering_element']['#value'] == t('Remove selected')) {
      foreach ($form_state['values']['table'] as $key => $row) {
        if (!empty($row)) {
          // Get selected row index.
          $row_index = str_replace("facet-row-", "", $key);
          // Unset the index to keep the other keys the same.
          unset($expore_config[$row_index]);
        }
      }
      $form_state['islandora_solr_facet_filters'] = $expore_config;
    }

    // Check if weights are being updated.
    if (isset($form_state['triggering_element']) && $form_state['triggering_element']['#value'] == t('Update weights') && !empty($expore_config)) {
      // Note: select_weight is only in the $form_state['input'] and doesn't
      // exist in $form_state['values'].
      $selected_weights = $form_state['input']['select_weight'];
      foreach ($selected_weights as $index => $weight) {
        $expore_config[$index]['weight'] = $weight;
      }
      // Sort config array by weight and update drupal variable.
      uasort($expore_config, 'drupal_sort_weight');
      $form_state['islandora_solr_facet_filters'] = $expore_config;
    }

    // Check if new display facet is being added to the table.
    if (isset($form_state['triggering_element']) && $form_state['triggering_element']['#id'] == 'facet-filter-add-more') {
      $duplicate_label = FALSE;
      $duplicate_filter = FALSE;
      $duplicate = FALSE;
      $facet_label = $form_state['values']['label'];
      $facet_filter = $form_state['values']['filter'];
      $facet_weight = $form_state['values']['facet_weight'];

      if (empty($facet_filter)) {
        form_set_error('filter', t('Facet Filter is required to add a display facet.'));
      }

      if (empty($facet_label)) {
        form_set_error('label', t('Display Label is required to add a display facet.'));
      }

      if (!empty($facet_label) && !empty($expore_config)) {
        foreach ($expore_config as $row) {
          if ($row['label'] == $facet_label) {
            // Label exists return form error.
            $duplicate_label = TRUE;
            break;
          }
          elseif ($row['filter'] == $facet_filter) {
            // Facet filter exists return form error.
            $duplicate_filter = TRUE;
            break;
          }
        }
        if ($duplicate_label) {
          form_set_error('label', t('Display Label must be unique.'));
          $duplicate = TRUE;
        }
        elseif ($duplicate_filter) {
          form_set_error('filter', t('Facet Filter must be unique.'));
          $duplicate = TRUE;
        }
      }
      if (!empty($facet_label) && !empty($facet_filter) && !$duplicate) {
        // Before it's added try to run the facet query and see if it's valid.
        $facet_is_valid = TRUE;

        if (!empty($facet_filter)) {
          module_load_include('inc', 'islandora_solr', 'includes/explore');

          // Store current messages.
          $old_msg = drupal_get_messages();

          // Clear current error messages.
          drupal_get_messages('error', TRUE);

          // Run a facet query on the supplied filter.
          islandora_solr_explore_test_facet_query($facet_filter);

          // Clear error messages run facet query and check if there are any
          // error messages.  If there are error messages assume that the query
          // failed and set facet to invalid.
          $error_msgs = drupal_get_messages('error');
          if (isset($error_msgs['error'])) {
            $facet_is_valid = FALSE;
          }
          // Restore the original messages.
          $_SESSION['messages'] = $old_msg;
          if (!$facet_is_valid) {
            form_set_error('filter', t('Invalid Facet Query Filter.'));
          }
        }

        if ($facet_is_valid) {
          // Put values into config array.
          $expore_config[$facet_filter] = [
            'label' => $facet_label,
            'filter' => $facet_filter,
            'weight' => $facet_weight,
          ];

          // Sort config array by weight and update drupal variable.
          uasort($expore_config, 'drupal_sort_weight');

          // Reset input values on successful add.
          $form_state['input']['label'] = '';
          $form_state['input']['filter'] = '';
          $form_state['input']['facet_weight'] = 0;
          $form_state['islandora_solr_facet_filters'] = $expore_config;
        }
      }
    }

    // Set table row data to be rendered.
    $rows = [];
    if (!empty($expore_config)) {
      foreach ($expore_config as $row_index => $row) {
        $rows["facet-row-$row_index"] = [
          'display_label' => htmlentities($row['label']),
          'facet_filter' => htmlentities($row['filter']),
          'facet_weight' => [
            'data' => [
              '#type' => 'select',
              '#title' => t('Weight'),
              '#options' => array_combine(range(-50, 50), range(-50, 50)),
              '#value' => (isset($row['weight']) ? $row['weight'] : 0),
              '#title_display' => 'invisible',
              '#attributes' => [
                'name' => "select_weight[{$row['filter']}]",
              ],
            ],
          ],
        ];
      }
    }

    $form['facet'] = [
      '#type' => 'fieldset',
      '#prefix' => '<div id="islandora-solr-facet-filter-wrapper">',
      '#suffix' => '</div>',
      '#title' => t('Setup Display Facets'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];
    $form['facet']['table'] = array(
      '#type' => 'tableselect',
      '#header' => array(
        'display_label' => t('Display Label'),
        'facet_filter' => t('Facet Query Filter'),
        'facet_weight' => t('Weight'),
      ),
      '#options' => $rows,
      '#empty' => t('No display facets'),
      '#attributes' => array('class' => ['main-facet-table']),
    );
    $form['facet']['actions'] = array(
      '#type' => 'actions',
      '#weight' => 5,
    );
    $form['facet']['actions']['remove'] = array(
      '#type' => 'button',
      '#value' => t('Remove selected'),
      '#weight' => 0,
      '#ajax' => array(
        'callback' => '_islandora_solr_update_filter_table',
        'wrapper' => 'islandora-solr-facet-filter-wrapper',
      ),
    );
    $form['facet']['actions']['update_weight'] = array(
      '#type' => 'button',
      '#value' => t('Update weights'),
      '#weight' => 1,
      '#ajax' => array(
        'callback' => '_islandora_solr_update_filter_table',
        'wrapper' => 'islandora-solr-facet-filter-wrapper',
      ),
    );
    $form['facet']['fieldset'] = array(
      '#type' => 'fieldset',
      '#title' => t('Add New Display Facet Details'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#weight' => 13,
    );
    $form['facet']['fieldset']['label'] = array(
      '#type' => 'textfield',
      '#title' => t('Display Label'),
      '#size' => 60,
      '#description' => t('The text displayed for the generated link.'),
      '#attributes' => array('class' => array('new-facet-label')),
    );
    // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $form['facet']['fieldset']['filter'] = array(
//       '#type' => 'textarea',
//       '#title' => t('Facet Query Filter'),
//       '#size' => 60,
//       '#description' => t("Write a custom facet query, for information on available tags see:") . l(t('Solr Luke Schema.'), "{$GLOBALS['base_url']}:8080/solr/admin/luke"),
//       '#attributes' => array('class' => array('new-facet-filter')),
//     );

    $form['facet']['fieldset']['facet_weight'] = array(
      '#type' => 'select',
      '#title' => t('Facet weight'),
      '#description' => t('Display weight'),
      '#options' => array_combine(range(-50, 50), range(-50, 50)),
      '#default_value' => 0,
      '#attributes' => array('class' => array('new-facet-weight')),
    );
    $form['facet']['fieldset']['add'] = array(
      '#type' => 'button',
      '#value' => t('Add'),
      '#attributes' => array('class' => array('islandora-solr-add-more-submit')),
      '#id' => 'facet-filter-add-more',
      '#ajax' => array(
        'callback' => '_islandora_solr_update_filter_table',
        'wrapper' => 'islandora-solr-facet-filter-wrapper',
      ),
    );
    // Process submit and variable set the new config on submit.
    $form['#submit'][] = 'islandora_solr_update_filters_on_submit';
  }
}

/**
 * On submit of the explore block config save the facet filters in form state.
 */
function islandora_solr_update_filters_on_submit($form, &$form_state) {
  if (isset($form_state['islandora_solr_facet_filters'])) {
    \Drupal::configFactory()->getEditable('islandora_solr.settings')->set('islandora_solr_explore_config', $form_state['islandora_solr_facet_filters'])->save();
  }
}

/**
 * Ajax Callback to reload the facet form when adding rows to the table.
 */
function _islandora_solr_update_filter_table($form, &$form_state) {
  return $form['facet'];
}

/**
 * AJAX callback for advanced search form.
 *
 * @param array $form
 *   An associative array containing form structure.
 * @param array $form_state
 *   An associative array containing form state.
 *
 * @return array
 *   An associative array containing the fully built form element.
 *
 * @see islandora_solr_advanced_search_form()
 */
function _islandora_solr_advanced_search_terms($form, &$form_state) {
  return $form['terms'];
}

/**
 * Provides list to change primary displays.
 *
 * Only populated with displays that are enabled through the admin form.
 *
 * @global IslandoraSolrQueryProcessor $_islandora_solr_queryclass
 *   The IslandoraSolrQueryProcessor object which includes the current query
 *   settings and the raw Solr results.
 *
 * @return string
 *   A rendered list containing enabled primary display links.
 */
function islandora_solr_display() {
  global $_islandora_solr_queryclass;

  $output = '';

  // Check if the page is dealing with search results.
  if (islandora_solr_results_page($_islandora_solr_queryclass)) {

    // Parameters set in URL.
    $params = isset($_islandora_solr_queryclass->internalSolrParams) ? $_islandora_solr_queryclass->internalSolrParams : array();
    $path = \Drupal\Core\Url::fromRoute("<current>")->toString();

    $profiles = \Drupal::moduleHandler()->invokeAll("islandora_solr_primary_display");
    // Get the table settings.
    // @FIXME
// Could not extract the default value because it is either indeterminate, or
// not scalar. You'll need to provide a default value in
// config/install/islandora_solr.settings.yml and config/schema/islandora_solr.schema.yml.
$primary_display_array = \Drupal::config('islandora_solr.settings')->get('islandora_solr_primary_display_table');

    if (!empty($primary_display_array)) {

      $weight = $primary_display_array['weight'];
      $enabled = $primary_display_array['enabled'];
      $default = $primary_display_array['default'];

      // Sort by weight.
      array_multisort(array_values($weight), SORT_ASC, array_keys($weight), SORT_ASC, $weight);

      $list_items = array();

      foreach ($weight as $key => $value) {
        if ($enabled[$key] !== 0) {
          if (isset($params['display'])) {
            $current_display = $params['display'];
          }
          else {
            $current_display = $default;
          }

          $display_name = $profiles[$key]['name'];

          $display_description = isset($profiles[$key]['description']) ? $profiles[$key]['description'] : NULL;

          $query = array_merge($params, array('display' => $key));

          $attr = array();
          $attr['class'] = 'display-' . \Drupal\Component\Utility\Unicode::strtolower(preg_replace('/[^A-Za-z0-9]/', '-', $key));
          if ($key == $current_display) {
            $attr['class'] .= ' active';
          }
          $attr['rel'] = 'nofollow';
          $attr['title'] = $display_description;
          // @FIXME
// url() expects a route name or an external URI.
// $attr['href'] = url($path, array('query' => $query));


          // XXX: We're not using l() because of
          // @link http://drupal.org/node/41595 active classes. @endlink
          $item = '<a' . drupal_attributes($attr) . '>' . \Drupal\Component\Utility\Html::escape($display_name) . '</a>';

          // Add link to list.
          $list_items[] = $item;
        }
      }
      // Theme item list (if more than one displays are available).
      if (count($list_items) > 1) {
        // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// $output = theme('item_list', array(
//           'items' => $list_items,
//           'title' => NULL,
//           'type' => 'ul',
//           'attributes' => array('class' => 'islandora-solr-display'),
//         ));

      }
    }
  }
  return $output;
}


/**
 * Provides a list to change the sorting on a current search query.
 *
 * @global IslandoraSolrQueryProcessor $_islandora_solr_queryclass
 *   The IslandoraSolrQueryProcessor object which includes the current query
 *   settings and the raw Solr results.
 *
 * @return string
 *   A rendered list containing Solr sort links.
 */
function islandora_solr_sort() {
  module_load_include('inc', 'islandora', 'includes/utilities');
  global $_islandora_solr_queryclass;

  $output = '';

  // Check if the page is dealing with search results.
  if (islandora_solr_results_page($_islandora_solr_queryclass)) {

    // Parameters set in URL.
    $params = isset($_islandora_solr_queryclass->internalSolrParams) ? $_islandora_solr_queryclass->internalSolrParams : array();

    $path = \Drupal\Core\Url::fromRoute("<current>")->toString();

    $sort_terms = islandora_solr_get_fields('sort_fields');

    // Get current sort (check in the actual parameters used for the query,
    // because modules might have altered it).
    if (isset($_islandora_solr_queryclass->solrParams['sort'])) {
      $sort_explode = preg_split(
        ISLANDORA_SOLR_QUERY_SPLIT_REGEX,
        $_islandora_solr_queryclass->solrParams['sort']
      );
      $current_sort_term = stripslashes($sort_explode[0]);
      $current_sort_order = isset($sort_explode[1]) ? $sort_explode[1] : NULL;
    }
    else {
      // If no parameters are given, internally solr defaults to 'score desc'
      // http://wiki.apache.org/solr/CommonQueryParameters#sort
      $current_sort_term = 'score';
      $current_sort_order = 'desc';
    }

    $list_items = array();

    foreach ($sort_terms as $term => $label) {
      $indicator = '';

      // Create query and indicator arrow.
      if ($term == $current_sort_term) {
        if (isset($current_sort_order) && $current_sort_order == 'asc') {
          $order = 'desc';
          // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// $indicator = theme('tablesort_indicator', array('style' => 'desc'));

        }
        else {
          $order = 'asc';
          // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// $indicator = theme('tablesort_indicator', array('style' => 'asc'));

        }
      }
      // The term is score or a date, we want descending order by default.
      elseif ($term == 'score' || stripos($term, 'date')) {
        $order = 'desc';
      }
      else {
        $order = 'asc';
      }
      $sort_param = $term . ' ' . $order;

      $query = array_merge($params, array('sort' => $sort_param));

      $attributes = array(
        'sort' => array(
          'attr' => array(
            'class' => array(),
          ),
          'query' => $query,
          'path' => $path,
        ),
      );
      $attr =& $attributes['sort']['attr'];
      if ($term == $current_sort_term) {
        $attr['class'] = array('active');
      }
      $attr['rel'] = 'nofollow';
      $attr['title'] = $label;
      // @FIXME
// url() expects a route name or an external URI.
// $attr['href'] = url($path, array('query' => $query));


      $hooks = islandora_build_hook_list(ISLANDORA_SOLR_FACET_BUCKET_CLASSES_HOOK_BASE);
      \Drupal::moduleHandler()->alter($hooks, $attributes, $_islandora_solr_queryclass);

      // XXX: We're not using l() because of
      // @link http://drupal.org/node/41595 active classes. @endlink
      $item = '<a' . drupal_attributes($attr) . '>' . $label . ' ' . $indicator . '</a>';

      // Add link to list.
      $list_items[] = $item;
    }

    // Theme item list.
    if (!empty($list_items)) {
      // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// $output = theme('item_list', array(
//         'items' => $list_items,
//         'title' => NULL,
//         'type' => 'ul',
//         'attributes' => array('class' => 'islandora-solr-sort'),
//       ));

    }
  }

  return $output;
}

/**
 * Search results navigation block.
 *
 * Pull params out of the URL to determine search id
 * and object offset.
 *
 * We use offset because the pid of a search result can be altered.
 * eg: Book pages
 *
 * @return string
 *   The rendered html for the block content.
 */
function islandora_solr_search_navigation() {
  $return = NULL;
  $params = \Drupal\Component\Utility\UrlHelper::filterQueryParameters();
  if (\Drupal::config('islandora_solr.settings')->get('islandora_solr_search_navigation') &&
    isset($params['solr_nav']['id']) &&
    isset($params['solr_nav']['page']) &&
    isset($params['solr_nav']['offset']) &&
    isset($_SESSION['islandora_solr_search_nav_params'][$params['solr_nav']['id']])) {
    $nav_params = $_SESSION['islandora_solr_search_nav_params'][$params['solr_nav']['id']];

    $prev_solr_object = NULL;
    $prev_offset = NULL;
    $prev_page = NULL;
    $next_solr_object = NULL;
    $next_offset = NULL;
    $next_page = NULL;
    $offset = $params['solr_nav']['offset'];
    $page = $params['solr_nav']['page'];
    $query_processor = new IslandoraSolrQueryProcessor();
    $query_processor->solrQuery = $nav_params['query'];
    $query_processor->internalSolrQuery = $nav_params['query_internal'];
    $query_processor->solrLimit = $nav_params['limit'];
    $query_processor->internalSolrParams = $nav_params['params_internal'];
    $query_processor->solrParams = $nav_params['params'];
    $limit = $nav_params['limit'];
    $query_processor->solrStart = $page * $limit;

    // Get solr objects for current page.
    $query_processor->executeQuery();

    $objects = array();
    if (!empty($query_processor->islandoraSolrResult)) {
      $objects = $query_processor->islandoraSolrResult['response']['objects'];
    }

    // Try get previous object on the current page.
    if ($offset != 0) {
      if (isset($objects[$offset - 1])) {
        $prev_solr_object = $objects[$offset - 1];
        $prev_offset = $offset - 1;
        $prev_page = $page;
      }
    }

    // If previous object can't be found on current page,
    // go to the previous page and select the last object.
    if (!$prev_solr_object && $page != 0) {
      $query_processor->solrStart = ($page - 1) * $limit;
      $query_processor->executeQuery();
      if (!empty($query_processor->islandoraSolrResult) && !empty($query_processor->islandoraSolrResult['response']['objects'])) {
        $prev_objects = $query_processor->islandoraSolrResult['response']['objects'];
        end($prev_objects);
        $prev_offset = key($prev_objects);
        $prev_page = $page - 1;
        $prev_solr_object = $prev_objects[$prev_offset];
      }
    }

    // Try get next object on the current page.
    if (isset($objects[$offset + 1])) {
      $next_solr_object = $objects[$offset + 1];
      $next_offset = $offset + 1;
      $next_page = $page;
    }

    // If next object can't be found on current page,
    // go to the next page and select the first object.
    if (!$next_solr_object) {
      $limit = $query_processor->solrLimit;
      $query_processor->solrStart = ($page + 1) * $limit;
      $query_processor->executeQuery();
      if (!empty($query_processor->islandoraSolrResult) && !empty($query_processor->islandoraSolrResult['response']['objects'])) {
        $next_objects = $query_processor->islandoraSolrResult['response']['objects'];
        $next_offset = 0;
        $next_page = $page + 1;
        $next_solr_object = $next_objects[$next_offset];
      }
    }

    $return_link = NULL;
    $prev_link = NULL;
    $next_link = NULL;
    // Get URL details from the prev and next objects
    // to populate navigation links.
    if (isset($nav_params['path'])) {
      $return_query = array_merge($query_processor->internalSolrParams, array('page' => $page));
      // @FIXME
// url() expects a route name or an external URI.
// $return_link = url($nav_params['path'], array('query' => $return_query, 'absolute' => TRUE));

    }
    if ($prev_solr_object) {
      $prev_params = $prev_solr_object['object_url_params'];
      $prev_params['solr_nav']['offset'] = $prev_offset;
      $prev_params['solr_nav']['page'] = $prev_page;
      $prev_params['solr_nav']['id'] = $params['solr_nav']['id'];
      // @FIXME
// url() expects a route name or an external URI.
// $prev_link = url($prev_solr_object['object_url'], array(
//         'query' => $prev_params,
//         'fragment' => (isset($prev_solr_object['object_url_fragment']) ? $prev_solr_object['object_url_fragment'] : ''),
//         'absolute' => TRUE,
//       ));


    }

    if ($next_solr_object) {
      $next_params = $next_solr_object['object_url_params'];
      $next_params['solr_nav']['offset'] = $next_offset;
      $next_params['solr_nav']['page'] = $next_page;
      $next_params['solr_nav']['id'] = $params['solr_nav']['id'];
      // @FIXME
// url() expects a route name or an external URI.
// $next_link = url($next_solr_object['object_url'], array(
//         'query' => $next_params,
//         'fragment' => (isset($next_solr_object['object_url_fragment']) ? $next_solr_object['object_url_fragment'] : ''),
//         'absolute' => TRUE,
//       ));

    }
    // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// $return = theme('islandora_solr_search_navigation_block', array(
//       'return_link' => $return_link,
//       'return_text' => t('Return to search'),
//       'prev_link' => $prev_link,
//       'prev_text' => t('Previous Object'),
//       'next_link' => $next_link,
//       'next_text' => t('Next Object'),
//     ));

  }
  return $return;
}

/**
 * Search results page size block.
 *
 * Use the limit URL param to increase or decrease the result page size.
 *
 * @return string
 *   The rendered html for the block content.
 */
function islandora_solr_search_results_limit() {
  global $_islandora_solr_queryclass;
  // Check if the page is dealing with search results.
  if (islandora_solr_results_page($_islandora_solr_queryclass)) {
    $default_limit = \Drupal::config('islandora_solr.settings')->get('islandora_solr_num_of_results');
    // @FIXME
// Could not extract the default value because it is either indeterminate, or
// not scalar. You'll need to provide a default value in
// config/install/islandora_solr.settings.yml and config/schema/islandora_solr.schema.yml.
$limits = \Drupal::config('islandora_solr.settings')->get('islandora_solr_num_of_results_advanced');
    $params = \Drupal\Component\Utility\UrlHelper::filterQueryParameters();
    $path = \Drupal\Core\Url::fromRoute("<current>")->toString();
    if (isset($params['limit']) && is_numeric($params['limit'])) {
      $default_limit = $params['limit'];
    }
    $limit_list = array();
    foreach ($limits as $limit) {
      if ($limit == $default_limit) {
        $limit_list[] = $limit;
      }
      else {
        $attr = array();
        $attr['rel'] = 'nofollow';
        $query = $params;
        $query['limit'] = $limit;
        unset($query['page']);
        // @FIXME
// url() expects a route name or an external URI.
// $attr['href'] = url($path, array('query' => $query));

        // XXX: We're not using l() because of
        // @link http://drupal.org/node/41595 active classes. @endlink
        $limit_list[] = '<a' . drupal_attributes($attr) . '>' . $limit . '</a>';
      }
    }

    if (!empty($limit_list)) {
      // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// return theme('item_list', array(
//         'items' => $limit_list,
//         'title' => NULL,
//         'type' => 'ul',
//         'attributes' => array('id' => 'islandora_solr_results_limit'),
//       ));

    }
  }
}
