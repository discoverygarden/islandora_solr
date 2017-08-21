<?php

/**
 * @file
 * Contains \Drupal\islandora_solr\Form\IslandoraSolrAdminSettings.
 */

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class IslandoraSolrAdminSettings extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_admin_settings';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // Add admin form css.
  // @FIXME
// drupal_set_title() has been removed. There are now a few ways to set the title
// dynamically, depending on the situation.
// 
// 
// @see https://www.drupal.org/node/2067859
// drupal_set_title(t('Solr settings'));


    $form['#attached'] = [
      'css' => [
        drupal_get_path('module', 'islandora_solr') . '/css/islandora_solr.admin.css'
        ],
      'library' => [['system', 'ui.dialog']],
      'js' => [
        drupal_get_path('module', 'islandora_solr') . '/js/islandora_solr.admin.js'
        ],
    ];
    $form['islandora_solr_tabs'] = [
      '#type' => 'vertical_tabs',
      '#weight' => 5,
    ];
    // Display profiles.
    $form['display_profiles'] = [
      '#type' => 'fieldset',
      '#title' => t('Display profiles'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    ];
    // Primary displays.
    $form['display_profiles']['islandora_solr_primary_display_table'] = [
      '#type' => 'item',
      '#title' => t('Primary display profiles'),
      '#description' => t('Preferred normal display profile for search results. These may be provided by third-party modules.'),
      // This attribute is important to return the submitted values in a deeper
      // nested arrays in.
    '#tree' => TRUE,
      '#theme' => 'islandora_solr_admin_primary_display',
    ];

    // Get the table settings.
    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/islandora_solr.settings.yml and config/schema/islandora_solr.schema.yml.
    $primary_display_array = \Drupal::config('islandora_solr.settings')->get('islandora_solr_primary_display_table');
    // Get all defined primary displays.
    $profiles = \Drupal::moduleHandler()->invokeAll("islandora_solr_primary_display");

    // If any primary display profiles are found.
    if (!empty($profiles)) {
      $profiles_sorted = [];
      // If the table settings are set, we change the order of the table rows.
      if (!empty($primary_display_array)) {
        // Set weight variable.
        $weight = $primary_display_array['weight'];
        // First sort by value and then sort equal values by key:
        // @see http://stackoverflow.com/a/6611077/477949
        array_multisort(array_values($weight), SORT_ASC, array_keys($weight), SORT_ASC, $weight);
        // Add all previously existing profiles with a weight...
        foreach (array_intersect_key($weight, $profiles) as $key => $value) {
          $profiles_sorted[$key] = $profiles[$key];
        }
        // Account for new profiles.
        foreach (array_diff_key($profiles, $profiles_sorted) as $key => $value) {
          $profiles_sorted[$key] = $value;
          // Add weight for new profile (heaviest +1).
          $primary_display_array['weight'][$key] = end($weight) + 1;
        }
      }
        // Or else use the default.
      else {
        // Only apply when there's no sort variable available.
      // Sort by key.
        ksort($profiles);
        $profiles_sorted = $profiles;
      }

      // Table loop.
      foreach ($profiles_sorted as $machine_name => $profile) {
        // Incremetally add every display profile to the options array.
        $options[$machine_name] = '';

        // Human name.
        $form['display_profiles']['islandora_solr_primary_display_table']['name'][$machine_name] = [
          '#type' => 'item',
          '#markup' => $profile['name'],
        ];
        // Machine name.
        $form['display_profiles']['islandora_solr_primary_display_table']['machine_name'][$machine_name] = [
          '#type' => 'item',
          '#markup' => $machine_name,
        ];
        // Weight.
        $form['display_profiles']['islandora_solr_primary_display_table']['weight'][$machine_name] = [
          '#type' => 'weight',
          '#default_value' => (isset($primary_display_array['weight'][$machine_name])) ? $primary_display_array['weight'][$machine_name] : 0,
          '#attributes' => [
            'class' => [
              'solr-weight'
              ]
            ],
        ];
        // Configuration URL.
        // @FIXME
        // l() expects a Url object, created from a route name or external URI.
        // $form['display_profiles']['islandora_solr_primary_display_table']['configuration'][$machine_name] = array(
        //         '#type' => 'item',
        //         '#markup' => (isset($profile['configuration']) && $profile['configuration'] != '') ? l(t('configure'), $profile['configuration']) : '',
        //       );

      }
      // Default display.
      $form['display_profiles']['islandora_solr_primary_display_table']['default'] = [
        '#type' => 'radios',
        '#options' => $options,
        '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_primary_display'),
      ];
      // Enabled display.
      $form['display_profiles']['islandora_solr_primary_display_table']['enabled'] = [
        '#type' => 'checkboxes',
        '#options' => $options,
        '#default_value' => (!empty($primary_display_array)) ? $primary_display_array['enabled'] : [],
      ];
    }

    // Secondary profiles.
    $profiles = \Drupal::moduleHandler()->invokeAll("islandora_solr_secondary_display");
    ksort($profiles);
    foreach ($profiles as $machine_name => $profile) {
      $islandora_solr_secondary_display_options[$machine_name] = $profile['name'];
    }
    if (!empty($islandora_solr_secondary_display_options)) {
      // @FIXME
// Could not extract the default value because it is either indeterminate, or
// not scalar. You'll need to provide a default value in
// config/install/islandora_solr.settings.yml and config/schema/islandora_solr.schema.yml.
      $form['display_profiles']['islandora_solr_secondary_display'] = [
        '#type' => 'checkboxes',
        '#title' => t('Secondary display profiles'),
        '#options' => $islandora_solr_secondary_display_options,
        '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_secondary_display'),
        '#description' => "Enabled secondary output/download types for search results.",
      ];
    }

    // Default display settings.
    $form['islandora_solr_tabs']['default_display_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Default display settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];
    // Display fields.
    $terms = [
      '#type' => 'item',
      '#title' => t('Display fields'),
      '#description' => t('Set labels for Solr fields to be included in the search results.'),
      '#tree' => TRUE,
      '#prefix' => '<div id="islandora-solr-result-fields-wrapper">',
      '#suffix' => '</div>',
      '#field_type' => 'result_fields',
      '#theme' => 'islandora_solr_admin_fields',
    ];

    // Create terms/fields.
    islandora_solr_admin_settings_fields($form_state, $terms, 'result_fields');

    // Result fields.
    $form['islandora_solr_tabs']['default_display_settings']['islandora_solr_result_fields'] = $terms;

    // Other results settings.
    $form['islandora_solr_tabs']['default_display_settings']['islandora_solr_limit_result_fields'] = [
      '#type' => 'checkbox',
      '#title' => t('Limit results to fields listed above?'),
      '#return_value' => 1,
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_limit_result_fields'),
      '#description' => t('If checked, results displayed will be limited to the fields specified above. The order of the display fields is only enforced when this is enabled.<br /><strong>Note:</strong> some display profiles may not honour this value.'),
    ];
    $form['islandora_solr_tabs']['default_display_settings']['islandora_solr_num_of_results'] = [
      '#type' => 'textfield',
      '#title' => t('Results per page'),
      '#size' => 5,
      '#description' => t('Default number of results to show per page.'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_num_of_results'),
    ];
    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/islandora_solr.settings.yml and config/schema/islandora_solr.schema.yml.
    $form['islandora_solr_tabs']['default_display_settings']['islandora_solr_num_of_results_advanced'] = [
      '#type' => 'textfield',
      '#title' => t('Advanced results per page'),
      '#size' => 5,
      '#description' => t('Comma seperated list of integers to increase or decrease results per page.'),
      '#default_value' => implode(',', \Drupal::config('islandora_solr.settings')->get('islandora_solr_num_of_results_advanced')),
    ];
    $form['islandora_solr_tabs']['default_display_settings']['islandora_solr_search_navigation'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable search navigation block'),
      '#description' => t('Add navigation params to object links.'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_search_navigation'),
    ];
    $form['islandora_solr_tabs']['default_display_settings']['islandora_solr_search_field_value_separator'] = [
      '#type' => 'textfield',
      '#title' => t('Field value separator'),
      '#description' => t('Characters to separate values in multivalued fields. If left empty it will default to @value.', [
        '@value' => '", "'
        ]),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_search_field_value_separator'),
    ];
    $form['islandora_solr_tabs']['default_display_settings']['islandora_solr_search_truncated_field_value_separator'] = [
      '#type' => 'textfield',
      '#title' => t('Truncated field value separator'),
      '#description' => t('Characters to separate truncated values in multivalued fields. If left empty it will default to line break.'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_search_truncated_field_value_separator'),
    ];
    // Sort settings.
    $form['islandora_solr_tabs']['sort'] = [
      '#type' => 'fieldset',
      '#title' => t('Sort settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];
    // Sort terms.
    $sort_terms = [
      '#type' => 'item',
      '#title' => t('Sort fields'),
      '#description' => t('Indicates what fields should appear in the <strong>Islandora sort block</strong>. To sort on relevancy, use the \'score\' field.<br /><strong>Note:</strong> not all fields are sortable. For more information, check the <a href="!url">Solr documentation</a>.', [
        '!url' => 'http://wiki.apache.org/solr/CommonQueryParameters#sort'
        ]),
      '#tree' => TRUE,
      '#prefix' => '<div id="islandora-solr-sort-fields-wrapper">',
      '#suffix' => '</div>',
      '#field_type' => 'sort_fields',
      '#theme' => 'islandora_solr_admin_fields',
    ];

    // Create terms/fields.
    islandora_solr_admin_settings_fields($form_state, $sort_terms, 'sort_fields');

    // Sort fields.
    $form['islandora_solr_tabs']['sort']['islandora_solr_sort_fields'] = $sort_terms;

    // Facet settings.
    $form['islandora_solr_tabs']['facet_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Facet settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    // Facet terms.
    $facet_terms = [
      '#type' => 'item',
      '#title' => t('Facet fields'),
      '#description' => t('Indicate what fields should appear as <strong>facets</strong>.<br /><strong>Note:</strong> it is recommended to use non-tokenized Solr fields (full literal strings).'),
      '#tree' => TRUE,
      '#prefix' => '<div id="islandora-solr-facet-fields-wrapper">',
      '#suffix' => '</div>',
      '#field_type' => 'facet_fields',
      '#theme' => 'islandora_solr_admin_fields',
    ];

    // Create terms/fields.
    islandora_solr_admin_settings_fields($form_state, $facet_terms, 'facet_fields');

    // Facet fields.
    $form['islandora_solr_tabs']['facet_settings']['islandora_solr_facet_fields'] = $facet_terms;

    $form['islandora_solr_tabs']['facet_settings']['islandora_solr_facet_min_limit'] = [
      '#type' => 'textfield',
      '#title' => t('Minimum limit'),
      '#size' => 5,
      '#description' => t('The minimum number of results required to display a facet'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_facet_min_limit'),
    ];
    $form['islandora_solr_tabs']['facet_settings']['islandora_solr_facet_soft_limit'] = [
      '#type' => 'textfield',
      '#title' => t('Soft limit'),
      '#size' => 5,
      '#description' => t('The number of results which should be displayed initially. If there are more, then the a "Show more" button will allow the rest up to the value below to be displayed. Use 0 to disable.'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_facet_soft_limit'),
    ];
    $form['islandora_solr_tabs']['facet_settings']['islandora_solr_facet_max_limit'] = [
      '#type' => 'textfield',
      '#title' => t('Maximum limit'),
      '#size' => 5,
      '#description' => t('The maximum number of terms that should be returned to the user. For example, if there are 100 possible subjects in a faceted result you may wish to only return the top 10.'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_facet_max_limit'),
    ];

    // Advanced search block.
    $form['islandora_solr_tabs']['advanced_search_block'] = [
      '#type' => 'fieldset',
      '#title' => t('Advanced search block'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $search_terms = [
      '#type' => 'item',
      '#title' => t('Search terms'),
      '#description' => t('Indicates what fields should appear in the dropdown menu of terms for
      the <strong>Advanced Search Block</strong>.<br /><strong>Note:</strong>
      it is recommended to use tokenized fields and non-tokenized string fields
      will not match correctly.'),
      '#tree' => TRUE,
      '#prefix' => '<div id="islandora-solr-search-fields-wrapper">',
      '#suffix' => '</div>',
      '#field_type' => 'search_fields',
      '#theme' => 'islandora_solr_admin_fields',
    ];

    // Create terms/fields.
    islandora_solr_admin_settings_fields($form_state, $search_terms, 'search_fields');

    // Search fields.
    $form['islandora_solr_tabs']['advanced_search_block']['islandora_solr_search_fields'] = $search_terms;

    $form['islandora_solr_tabs']['advanced_search_block']['islandora_solr_search_boolean'] = [
      '#type' => 'radios',
      '#title' => t('Default boolean operator'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_search_boolean'),
      '#options' => [
        'user' => t('User-configurable'),
        'AND' => t('AND'),
        'OR' => t('OR'),
      ],
      '#description' => t('Select a default boolean operator for the search query. Selecting "User-configurable" exposes a dropdown menu which gives the user a choice between AND, OR and NOT.'),
    ];
    $form['islandora_solr_tabs']['advanced_search_block']['islandora_solr_allow_preserve_filters'] = [
      '#type' => 'checkbox',
      '#title' => t('Allow Preservation of Filters'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_allow_preserve_filters'),
      '#description' => t('Allow users to preserve filters when changing their search.'),
    ];
    $form['islandora_solr_tabs']['advanced_search_block']['islandora_solr_human_friendly_query_block'] = [
      '#type' => 'checkbox',
      '#title' => t('Human-friendly Current Query'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_human_friendly_query_block'),
      '#description' => t('Use labels instead of raw field names when displaying an advanced search in the Current Query block.'),
    ];

    $form['islandora_solr_tabs']['advanced_search_block']['islandora_solr_advanced_search_block_lucene_syntax_escape'] = [
      '#title' => t('Escape Lucene special characters'),
      '#description' => t('Allow the use of lucene syntax string escaping on search terms'),
      '#type' => 'checkbox',
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_advanced_search_block_lucene_syntax_escape'),
    ];

    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/islandora_solr.settings.yml and config/schema/islandora_solr.schema.yml.
    $form['islandora_solr_tabs']['advanced_search_block']['islandora_solr_advanced_search_block_lucene_regex_default'] = [
      '#type' => 'textfield',
      '#title' => t('Default regular expression evaluated on search term'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_advanced_search_block_lucene_regex_default'),
      '#description' => t("The default regular expression, used to escape characters when found in search terms. Defaults to @regex", [
        '@regex' => ISLANDORA_SOLR_QUERY_FACET_LUCENE_ESCAPE_REGEX_DEFAULT
        ]),
      '#states' => [
        'visible' => [
          ':input[name="islandora_solr_advanced_search_block_lucene_syntax_escape"]' => [
            'checked' => TRUE
            ]
          ]
        ],
    ];

    // Query defaults.
    $form['islandora_solr_tabs']['query_defaults'] = [
      '#type' => 'fieldset',
      '#title' => t('Query defaults'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];
    $form['islandora_solr_tabs']['query_defaults']['islandora_solr_namespace_restriction'] = [
      '#type' => 'textarea',
      '#title' => t('Limit results to specific namespaces'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_namespace_restriction'),
      '#description' => t("Enter a space- or comma-separated list of namespaces (for example, 'demo, default') to restrict results to PIDs within those namespaces."),
    ];
    $form['islandora_solr_tabs']['query_defaults']['islandora_solr_base_query'] = [
      '#type' => 'textfield',
      '#title' => t('Solr default query'),
      '#size' => 30,
      '#description' => t('A default query used to browse Solr results when no explicit user query is set.
      Setting a useful default query allows the use of Solr to browse without having to enter a query.
      This may be used in conjunction with a background filter below.<br />
      Consider using <strong>fgs_createdDate_dt:[* TO NOW]</strong> or <strong>*:*</strong><br />'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_base_query'),
    ];
    $form['islandora_solr_tabs']['query_defaults']['islandora_solr_base_advanced'] = [
      '#type' => 'checkbox',
      '#title' => t('Use default query for empty advanced searches too'),
      '#description' => t('When selected, an empty advanced search will perform the same as an empty simple search. If not selected, empty advanced searches will search *:*'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_base_advanced'),
    ];
    $form['islandora_solr_tabs']['query_defaults']['islandora_solr_base_sort'] = [
      '#type' => 'textfield',
      '#title' => t('Sort field for default query'),
      '#size' => 30,
      '#description' => t('Indicates which field should define the sort order for the default query.<br />
    For example: <strong>fgs_createdDate_dt desc</strong>.<br /><strong>Note:</strong> only single-valued fields are sortable.
    For more information, check the <a href="!url">Solr documentation</a>.', [
        '!url' => 'http://wiki.apache.org/solr/CommonQueryParameters#sort'
        ]),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_base_sort'),
    ];
    $form['islandora_solr_tabs']['query_defaults']['islandora_solr_base_filter'] = [
      '#type' => 'textarea',
      '#title' => t('Solr base filter'),
      '#description' => t('Lists base filters that are appended to all user queries. This may be used to filter results and facilitate null-query browsing. Enter one filter per line. <br />
      These filters will be applied to all queries in addition to any user-selected facet filters'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_base_filter'),
      '#wysiwyg' => FALSE,
    ];
    $form['islandora_solr_tabs']['query_defaults']['islandora_solr_query_fields'] = [
      '#type' => 'textarea',
      '#title' => t('Query fields'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_query_fields'),
      '#description' => t('<a href="@url" target="_blank" title="Solr query fields documentation">Query fields</a> to use for DisMax (simple) searches.', [
        '@url' => 'http://wiki.apache.org/solr/DisMaxQParserPlugin#qf_.28Query_Fields.29'
        ]),
    ];
    $form['islandora_solr_tabs']['query_defaults']['islandora_solr_use_ui_qf'] = [
      '#type' => 'checkbox',
      '#title' => t('Prefer defined query fields?'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_use_ui_qf'),
      '#description' => t('Use the above "@qf" by default; otherwise, they will only be used as a fallback in the case there are none defined in the selected request handler.', [
        '@qf' => t('Query fields')
        ]),
    ];

    // Required Solr fields.
    $form['islandora_solr_tabs']['required_solr_fields'] = [
      '#type' => 'fieldset',
      '#title' => t('Required Solr fields'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];
    // Content Model Solr field.
    $form['islandora_solr_tabs']['required_solr_fields']['islandora_solr_content_model_field'] = [
      '#type' => 'textfield',
      '#title' => t('Content model Solr field'),
      '#size' => 30,
      '#description' => t('Solr field containing the content model URIs. This should be a multivalued string field'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_content_model_field'),
      '#required' => TRUE,
    ];
    // Present datastreams Solr field.
    $form['islandora_solr_tabs']['required_solr_fields']['islandora_solr_datastream_id_field'] = [
      '#type' => 'textfield',
      '#title' => t('Datastream ID Solr field'),
      '#size' => 30,
      '#description' => t('Solr field containing the populated datastream IDs.
      This should be a multivalued string field. If this field is not populated,
      the DSID of TN will be assumed valid for thumbnails.'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_datastream_id_field'),
      '#required' => TRUE,
    ];
    // Label Solr field.
    $form['islandora_solr_tabs']['required_solr_fields']['islandora_solr_object_label_field'] = [
      '#type' => 'textfield',
      '#title' => t('Object label Solr field'),
      '#size' => 30,
      '#description' => t("The Solr field containing an object's label. This should be a single valued string field."),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_object_label_field'),
      '#required' => TRUE,
    ];
    // The isMemberOf Solr field.
    $form['islandora_solr_tabs']['required_solr_fields']['islandora_solr_member_of_field'] = [
      '#type' => 'textfield',
      '#title' => t('The isMemberOf Solr field'),
      '#size' => 30,
      '#description' => t("The Solr field containing an object's isMemberOf relationship"),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_member_of_field'),
      '#required' => TRUE,
    ];
    // The isMemberOfCollection Solr field.
    $form['islandora_solr_tabs']['required_solr_fields']['islandora_solr_member_of_collection_field'] = [
      '#type' => 'textfield',
      '#title' => t('The isMemberOfCollection Solr field'),
      '#size' => 30,
      '#description' => t("The Solr field containing an object's isMemberOfCollection relationship"),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_member_of_collection_field'),
      '#required' => TRUE,
    ];
    // Miscellaneous.
    $form['islandora_solr_tabs']['other'] = [
      '#type' => 'fieldset',
      '#title' => t('Other'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];
    // Debug mode.
    $form['islandora_solr_tabs']['other']['islandora_solr_debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => t('Debug mode?'),
      '#return_value' => 1,
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_debug_mode'),
      '#description' => t('Dumps Solr queries to the screen for testing. Warning: if you have the Drupal Apache Solr module enabled alongside this one, the debug function will not work.'),
      '#weight' => 6,
    ];
    // Luke timeout.
    $form['islandora_solr_tabs']['other']['islandora_solr_luke_timeout'] = [
      '#type' => 'textfield',
      '#title' => t('Solr Luke timeout'),
      '#size' => 10,
      '#description' => t("Number of seconds to set timeout for retrieving fields from Solr's Luke interface. Increase this if autocomplete fields containing Solr field names time out."),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_luke_timeout'),
      '#required' => TRUE,
    ];

    // The content of the popup dialog.
    $form['islandora_solr_admin_dialog'] = [
      '#theme_wrappers' => [
        'container'
        ],
      '#id' => 'islandora-solr-admin-dialog',
      '#weight' => 50,
    ];
    $form['islandora_solr_admin_dialog']['title'] = [
      '#markup' => '<h2 id="islandora-solr-admin-dialog-title"></h2>'
      ];
    $form['islandora_solr_admin_dialog']['body'] = [
      '#theme_wrappers' => [
        'container'
        ],
      '#id' => 'islandora-solr-admin-dialog-body',
      '#markup' => t('Default dialog text'),
    ];

    // Actions.
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save Solr configuration'),
      '#weight' => 0,
      '#submit' => [
        '_islandora_solr_admin_settings_submit'
        ],
      '#validate' => ['_islandora_solr_admin_settings_validate'],
    ];
    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => t('Reset to defaults'),
      '#weight' => 1,
      '#submit' => [
        '_islandora_solr_admin_settings_submit'
        ],
    ];

    if (!empty($_POST) && $form_state->getErrors()) {
      drupal_set_message(t('Error: the settings have not been saved.'), 'error');
    }
    return $form;
  }

}
?>
