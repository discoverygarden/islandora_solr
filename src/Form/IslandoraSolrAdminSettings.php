<?php

namespace Drupal\islandora_solr\Form;

use PDO;

use Drupal\Core\Form\FormStateInterface;
use Drupal\islandora\Form\ModuleHandlerAdminForm;

/**
 * Administration setting form.
 */
class IslandoraSolrAdminSettings extends ModuleHandlerAdminForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['islandora_solr.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form_state->loadInclude('islandora', 'inc', 'includes/utilities');
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/admin');
    $config = $this->config('islandora_solr.settings');
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    // Display profiles.
    $form['display_profiles'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Display profiles'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    ];
    // Primary displays.
    $form['display_profiles']['islandora_solr_primary_display_table'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [
        'default' => $this->t('Default'),
        'enabled' => $this->t('Enabled'),
        'name' => $this->t('Name'),
        'machine' => $this->t('Machine-Readable Name'),
        'configuration' => $this->t('Configuration'),
        'weight' => $this->t('Weight'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'islandora-solr-primary-display-table-order-weight',
        ],
      ],
      '#caption' => $this->t('<strong>Primary display profiles</strong>'),
    ];
    // XXX: Hidden to store the value of the default because tableselects are
    // still a bastard with values inside of them.
    $form['islandora_solr_primary_table_default_choice'] = [
      '#type' => 'value',
    ];
    $primary_display_array = $config->get('islandora_solr_primary_display_table');

    // Get all defined primary displays.
    $profiles = $this->moduleHandler->invokeAll("islandora_solr_primary_display");
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
        // Default display logic for re-use.
        $default = $config->get('islandora_solr_primary_display');
        $default_enabled = isset($primary_display_array['enabled'][$machine_name]) ? $primary_display_array['enabled'][$machine_name] : FALSE;
        if ($default == $machine_name) {
          $default_enabled = TRUE;
        }
        $form['display_profiles']['islandora_solr_primary_display_table'][$machine_name] = [
          'default' => [
            '#type' => 'radio',
            '#title' => $this->t('Default'),
            '#title_display' => 'invisible',
            '#name' => 'islandora_solr_primary_table_default_choice',
            '#return_value' => $machine_name,
            '#default_value' => $default == $machine_name ? $default : NULL,
            '#states' => [
              'disabled' => [
                ":input[name='islandora_solr_primary_display_table[{$machine_name}][enabled]']" => [
                  'checked' => FALSE,
                ],
              ],
            ],
          ],
          'enabled' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Enabled'),
            '#title_display' => 'invisible',
            '#default_value' => $default_enabled,
            '#states' => [
              'disabled' => [
                ':input[name="islandora_solr_primary_table_default_choice"]' => [
                  'value' => $machine_name,
                ],
              ],
            ],
          ],
          'name' => [
            '#plain_text' => $profile['name'],
          ],
          'machine' => [
            '#plain_text' => $machine_name,
          ],
        ];
        $form['display_profiles']['islandora_solr_primary_display_table'][$machine_name]['#attributes']['class'][] = 'draggable';
        if (isset($profile['configuration'])) {
          $form['display_profiles']['islandora_solr_primary_display_table'][$machine_name]['configuration'] = [
            '#title' => $this->t('configure'),
            '#type' => 'link',
            '#url' => islandora_get_url_from_path_or_route($profile['configuration']),
          ];
        }
        else {
          $form['display_profiles']['islandora_solr_primary_display_table'][$machine_name]['configuration'] = [];
        }
        $form['display_profiles']['islandora_solr_primary_display_table'][$machine_name]['weight'] = [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @title', ['@title' => $profile['name']]),
          '#title_display' => 'invisible',
          '#default_value' => isset($primary_display_array['weight'][$machine_name]) ? $primary_display_array['weight'][$machine_name] : 0,
          // Classify the weight element for #tabledrag.
          '#attributes' => ['class' => ['islandora-solr-primary-display-table-order-weight']],
        ];
      }
    }
    // Secondary profiles.
    $profiles = $this->moduleHandler->invokeAll("islandora_solr_secondary_display");
    ksort($profiles);
    foreach ($profiles as $machine_name => $profile) {
      $islandora_solr_secondary_display_options[$machine_name] = $profile['name'];
    }
    if (!empty($islandora_solr_secondary_display_options)) {
      $form['display_profiles']['islandora_solr_secondary_display'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Secondary display profiles'),
        '#options' => $islandora_solr_secondary_display_options,
        '#default_value' => $config->get('islandora_solr_secondary_display'),
        '#description' => $this->t('Enabled secondary output/download types for search results.'),
      ];
    }
    $form['islandora_solr_tabs'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'default-display-settings',
    ];
    // Default display settings.
    $form['default_display_settings'] = [
      '#type' => 'details',
      '#group' => 'islandora_solr_tabs',
      '#title' => $this->t('Default display settings'),
    ];
    // Display fields.
    $terms = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Display fields'),
      '#description' => $this->t('Set labels for Solr fields to be included in the search results. Displayed settings will update on save.'),
      '#tree' => TRUE,
      '#prefix' => '<div id="islandora-solr-result-fields-wrapper">',
      '#suffix' => '</div>',
      '#field_type' => 'result_fields',
    ];

    // Create terms/fields.
    islandora_solr_admin_settings_fields($form_state, $terms, 'result_fields');

    // Result fields.
    $form['default_display_settings']['islandora_solr_result_fields'] = $terms;

    // Other results settings.
    $form['default_display_settings']['islandora_solr_limit_result_fields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit results to fields listed above?'),
      '#return_value' => 1,
      '#default_value' => $config->get('islandora_solr_limit_result_fields'),
      '#description' => $this->t('If checked, results displayed will be limited to the fields specified above. The order of the display fields is only enforced when this is enabled.<br /><strong>Note:</strong> some display profiles may not honour this value.'),
    ];
    $form['default_display_settings']['islandora_solr_num_of_results'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Results per page'),
      '#size' => 5,
      '#description' => $this->t('Default number of results to show per page.'),
      '#default_value' => $config->get('islandora_solr_num_of_results'),
    ];
    $form['default_display_settings']['islandora_solr_num_of_results_advanced'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Advanced results per page'),
      '#size' => 5,
      '#description' => $this->t('Comma separated list of integers to increase or decrease results per page.'),
      '#default_value' => implode(',', $config->get('islandora_solr_num_of_results_advanced')),
    ];
    $form['default_display_settings']['islandora_solr_search_navigation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable search navigation block'),
      '#description' => $this->t('Add navigation params to object links.'),
      '#default_value' => $config->get('islandora_solr_search_navigation'),
    ];
    $form['default_display_settings']['islandora_solr_search_field_value_separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field value separator'),
      '#description' => $this->t('Characters to separate values in multivalued fields. If left empty it will default to @value.', [
        '@value' => '", "',
      ]),
      '#default_value' => $config->get('islandora_solr_search_field_value_separator'),
    ];
    $form['default_display_settings']['islandora_solr_search_truncated_field_value_separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Truncated field value separator'),
      '#description' => $this->t('Characters to separate truncated values in multivalued fields. If left empty it will default to line break.'),
      '#default_value' => $config->get('islandora_solr_search_truncated_field_value_separator'),
    ];
    // Sort settings.
    $form['sort'] = [
      '#type' => 'details',
      '#title' => $this->t('Sort settings'),
      '#group' => 'islandora_solr_tabs',
    ];
    // Sort terms.
    $sort_terms = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Sort fields'),
      '#description' => $this->t('Indicates what fields should appear in the <strong>Islandora sort block</strong>. To sort on relevancy, use the \'score\' field.<br /><strong>Note:</strong> not all fields are sortable. For more information, check the <a href="@url">Solr documentation</a>. Displayed settings will update on save.', [
        '@url' => 'http://wiki.apache.org/solr/CommonQueryParameters#sort',
      ]),
      '#tree' => TRUE,
      '#prefix' => '<div id="islandora-solr-sort-fields-wrapper">',
      '#suffix' => '</div>',
      '#field_type' => 'sort_fields',
    ];

    // Create terms/fields.
    islandora_solr_admin_settings_fields($form_state, $sort_terms, 'sort_fields');

    // Sort fields.
    $form['sort']['islandora_solr_sort_fields'] = $sort_terms;

    // Facet settings.
    $form['facet_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Facet settings'),
      '#group' => 'islandora_solr_tabs',
    ];

    // Facet terms.
    $facet_terms = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Facet fields'),
      '#description' => $this->t('Indicate what fields should appear as <strong>facets</strong>.<br /><strong>Note:</strong> it is recommended to use non-tokenized Solr fields (full literal strings). Displayed settings will update on save.'),
      '#tree' => TRUE,
      '#prefix' => '<div id="islandora-solr-facet-fields-wrapper">',
      '#suffix' => '</div>',
      '#field_type' => 'facet_fields',
    ];

    // Create terms/fields.
    islandora_solr_admin_settings_fields($form_state, $facet_terms, 'facet_fields');

    // Facet fields.
    $form['facet_settings']['islandora_solr_facet_fields'] = $facet_terms;

    $form['facet_settings']['islandora_solr_facet_min_limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Minimum limit'),
      '#size' => 5,
      '#description' => $this->t('The minimum number of results required to display a facet'),
      '#default_value' => $config->get('islandora_solr_facet_min_limit'),
    ];
    $form['facet_settings']['islandora_solr_facet_soft_limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Soft limit'),
      '#size' => 5,
      '#description' => $this->t('The number of results which should be displayed initially. If there are more, then the "Show more" button will allow the rest up to the value below to be displayed. Use 0 to disable.'),
      '#default_value' => $config->get('islandora_solr_facet_soft_limit'),
    ];
    $form['facet_settings']['islandora_solr_facet_max_limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum limit'),
      '#size' => 5,
      '#description' => $this->t('The maximum number of terms that should be returned to the user. For example, if there are 100 possible subjects in a faceted result you may wish to only return the top 10.'),
      '#default_value' => $config->get('islandora_solr_facet_max_limit'),
    ];

    // Advanced search block.
    $form['advanced_search_block'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced search block'),
      '#group' => 'islandora_solr_tabs',
    ];

    $search_terms = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Search terms'),
      '#description' => $this->t('Indicates what fields should appear in the dropdown menu of terms for
      the <strong>Advanced Search Block</strong>.<br /><strong>Note:</strong>
      it is recommended to use tokenized fields and non-tokenized string fields
      will not match correctly. Displayed settings will update on save.'),
      '#tree' => TRUE,
      '#prefix' => '<div id="islandora-solr-search-fields-wrapper">',
      '#suffix' => '</div>',
      '#field_type' => 'search_fields',
    ];

    // Create terms/fields.
    islandora_solr_admin_settings_fields($form_state, $search_terms, 'search_fields');

    // Search fields.
    $form['advanced_search_block']['islandora_solr_search_fields'] = $search_terms;

    $form['advanced_search_block']['islandora_solr_search_boolean'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default boolean operator'),
      '#default_value' => $config->get('islandora_solr_search_boolean'),
      '#options' => [
        'user' => $this->t('User-configurable'),
        'AND' => $this->t('AND'),
        'OR' => $this->t('OR'),
      ],
      '#description' => $this->t('Select a default boolean operator for the search query. Selecting "User-configurable" exposes a dropdown menu which gives the user a choice between AND, OR and NOT.'),
    ];
    $form['advanced_search_block']['islandora_solr_allow_preserve_filters'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow Preservation of Filters'),
      '#default_value' => $config->get('islandora_solr_allow_preserve_filters'),
      '#description' => $this->t('Allow users to preserve filters when changing their search.'),
    ];
    $form['advanced_search_block']['islandora_solr_human_friendly_query_block'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Human-friendly Current Query'),
      '#default_value' => $config->get('islandora_solr_human_friendly_query_block'),
      '#description' => $this->t('Use labels instead of raw field names when displaying an advanced search in the Current Query block.'),
    ];

    $form['advanced_search_block']['islandora_solr_advanced_search_block_lucene_syntax_escape'] = [
      '#title' => $this->t('Escape Lucene special characters'),
      '#description' => $this->t('Allow the use of lucene syntax string escaping on search terms'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('islandora_solr_advanced_search_block_lucene_syntax_escape'),
    ];

    $form['advanced_search_block']['islandora_solr_advanced_search_block_lucene_regex_default'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default regular expression evaluated on search term'),
      '#default_value' => $config->get('islandora_solr_advanced_search_block_lucene_regex_default'),
      '#description' => $this->t("The default regular expression, used to escape characters when found in search terms. Defaults to @regex", [
        '@regex' => ISLANDORA_SOLR_QUERY_FACET_LUCENE_ESCAPE_REGEX_DEFAULT,
      ]),
      '#states' => [
        'visible' => [
          ':input[name="islandora_solr_advanced_search_block_lucene_syntax_escape"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    // Query defaults.
    $form['query_defaults'] = [
      '#type' => 'details',
      '#title' => $this->t('Query defaults'),
      '#group' => 'islandora_solr_tabs',
    ];
    $form['query_defaults']['islandora_solr_namespace_restriction'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Limit results to specific namespaces'),
      '#default_value' => $config->get('islandora_solr_namespace_restriction'),
      '#description' => $this->t("Enter a space- or comma-separated list of namespaces (for example, 'demo, default') to restrict results to PIDs within those namespaces."),
    ];
    $form['query_defaults']['islandora_solr_base_query'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Solr default query'),
      '#size' => 30,
      '#description' => $this->t('A default query used to browse Solr results when no explicit user query is set.
      Setting a useful default query allows the use of Solr to browse without having to enter a query.
      This may be used in conjunction with a background filter below.<br />
      Consider using <strong>fgs_createdDate_dt:[* TO NOW]</strong> or <strong>*:*</strong><br />'),
      '#default_value' => $config->get('islandora_solr_base_query'),
    ];
    $form['query_defaults']['islandora_solr_base_advanced'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use default query for empty advanced searches too'),
      '#description' => $this->t('When selected, an empty advanced search will perform the same as an empty simple search. If not selected, empty advanced searches will search *:*'),
      '#default_value' => $config->get('islandora_solr_base_advanced'),
    ];
    $form['query_defaults']['islandora_solr_base_sort'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sort field for default query'),
      '#size' => 30,
      '#description' => $this->t('Indicates which field should define the sort order for the default query.<br />
    For example: <strong>fgs_createdDate_dt desc</strong>.<br /><strong>Note:</strong> only single-valued fields are sortable.
    For more information, check the <a href="@url">Solr documentation</a>.', [
      '@url' => 'http://wiki.apache.org/solr/CommonQueryParameters#sort',
    ]),
      '#default_value' => $config->get('islandora_solr_base_sort'),
    ];
    $form['query_defaults']['islandora_solr_base_filter'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Solr base filter'),
      '#description' => $this->t('Lists base filters that are appended to all user queries. This may be used to filter results and facilitate null-query browsing. Enter one filter per line. <br />
      These filters will be applied to all queries in addition to any user-selected facet filters'),
      '#default_value' => $config->get('islandora_solr_base_filter'),
      '#wysiwyg' => FALSE,
    ];
    $form['query_defaults']['islandora_solr_query_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Query fields'),
      '#default_value' => $config->get('islandora_solr_query_fields'),
      '#description' => $this->t('<a href="@url" target="_blank" title="Solr query fields documentation">Query fields</a> to use for DisMax (simple) searches.', [
        '@url' => 'http://wiki.apache.org/solr/DisMaxQParserPlugin#qf_.28Query_Fields.29',
      ]),
    ];
    $form['query_defaults']['islandora_solr_use_ui_qf'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Prefer defined query fields?'),
      '#default_value' => $config->get('islandora_solr_use_ui_qf'),
      '#description' => $this->t('Use the above "@qf" by default; otherwise, they will only be used as a fallback in the case there are none defined in the selected request handler.', [
        '@qf' => $this->t('Query fields'),
      ]),
    ];

    // Required Solr fields.
    $form['required_solr_fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Required Solr fields'),
      '#group' => 'islandora_solr_tabs',
    ];
    // Content Model Solr field.
    $form['required_solr_fields']['islandora_solr_content_model_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content model Solr field'),
      '#size' => 30,
      '#description' => $this->t('Solr field containing the content model URIs. This should be a multivalued string field'),
      '#default_value' => $config->get('islandora_solr_content_model_field'),
      '#required' => TRUE,
    ];
    // Present datastreams Solr field.
    $form['required_solr_fields']['islandora_solr_datastream_id_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Datastream ID Solr field'),
      '#size' => 30,
      '#description' => $this->t('Solr field containing the populated datastream IDs.
      This should be a multivalued string field. If this field is not populated,
      the DSID of TN will be assumed valid for thumbnails.'),
      '#default_value' => $config->get('islandora_solr_datastream_id_field'),
      '#required' => TRUE,
    ];
    // Label Solr field.
    $form['required_solr_fields']['islandora_solr_object_label_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Object label Solr field'),
      '#size' => 30,
      '#description' => $this->t("The Solr field containing an object's label. This should be a single valued string field."),
      '#default_value' => $config->get('islandora_solr_object_label_field'),
      '#required' => TRUE,
    ];
    // The isMemberOf Solr field.
    $form['required_solr_fields']['islandora_solr_member_of_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The isMemberOf Solr field'),
      '#size' => 30,
      '#description' => $this->t("The Solr field containing an object's isMemberOf relationship"),
      '#default_value' => $config->get('islandora_solr_member_of_field'),
      '#required' => TRUE,
    ];
    // The isMemberOfCollection Solr field.
    $form['required_solr_fields']['islandora_solr_member_of_collection_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The isMemberOfCollection Solr field'),
      '#size' => 30,
      '#description' => $this->t("The Solr field containing an object's isMemberOfCollection relationship"),
      '#default_value' => $config->get('islandora_solr_member_of_collection_field'),
      '#required' => TRUE,
    ];
    // Miscellaneous.
    $form['other'] = [
      '#type' => 'details',
      '#title' => $this->t('Other'),
      '#group' => 'islandora_solr_tabs',
    ];
    // Debug mode.
    $form['other']['islandora_solr_debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug mode?'),
      '#return_value' => 1,
      '#default_value' => $config->get('islandora_solr_debug_mode'),
      '#description' => $this->t('Dumps Solr queries to the screen for testing. Warning: if you have the Drupal Apache Solr module enabled alongside this one, the debug function will not work.'),
      '#weight' => 6,
    ];
    // Luke timeout.
    $form['other']['islandora_solr_luke_timeout'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Solr Luke timeout'),
      '#size' => 10,
      '#description' => $this->t("Number of seconds to set timeout for retrieving fields from Solr's Luke interface. Increase this if autocomplete fields containing Solr field names time out."),
      '#default_value' => $config->get('islandora_solr_luke_timeout'),
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $limits = explode(',', $form_state->getValue('islandora_solr_num_of_results_advanced'));
    $form_state->setValue('islandora_solr_num_of_results_advanced', array_filter(array_map('trim', $limits), 'is_numeric'));
    $form_state->setValue('islandora_solr_namespace_restriction', preg_replace('/:$/', '', $form_state->getValue('islandora_solr_namespace_restriction')));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // XXX: To preserve backwards compatability of the primary display table
    // need to munge the data into the Drupal 7 form.
    $munged_config = [
      'weight' => [],
      'default' => $form_state->getValue('islandora_solr_primary_table_default_choice'),
      'enabled' => [],
    ];
    foreach ($form_state->getValue('islandora_solr_primary_display_table') as $key => $values) {
      $munged_config['weight'][$key] = $values['weight'];
      $munged_config['enabled'][$key] = ($munged_config['default'] == $key) ? TRUE : $values['enabled'];
    }
    $this->config('islandora_solr.settings')->set('islandora_solr_primary_display_table', $munged_config);
    $this->config('islandora_solr.settings')->set('islandora_solr_primary_display', $munged_config['default']);
    // Skip values that are part of the form state object or need combination
    // for special handling.
    $skipped_keys = [
      'islandora_solr_primary_display_table',
      'op',
      'form_build_id',
      'form_token',
      'form_id',
      'submit',
      'islandora_solr_result_fields',
      'islandora_solr_sort_fields',
      'islandora_solr_facet_fields',
      'islandora_solr_search_fields',
    ];
    foreach ($form_state->getValues() as $key => $values) {
      if (!in_array($key, $skipped_keys)) {
        $this->config('islandora_solr.settings')->set($key, $values);
      }
    }
    $this->config('islandora_solr.settings')->save();

    // Handle fields.
    $current_values = [];
    $field_types = [
      'result_fields',
      'sort_fields',
      'facet_fields',
      'search_fields',
    ];
    foreach ($field_types as $field_type) {
      if ($form_state->getValue(["islandora_solr_{$field_type}", 'terms'])) {
        $result_fields = $form_state->getValue(["islandora_solr_{$field_type}", 'terms']);
        foreach ($result_fields as $key => $value) {
          $solr_field = $value['solr_field'];
          $solr_field_settings = [];
          if ($form_state->get(['solr_field_settings',
            "islandora_solr_{$field_type}",
            $solr_field,
          ])) {
            $solr_field_settings = $form_state->get(['solr_field_settings',
              "islandora_solr_{$field_type}",
              $solr_field,
            ]);
            // Handle linking to objects to not break existing features while
            // adding new functionality.
            if ($field_type == 'result_fields') {
              if (isset($solr_field_settings['link_rendering'])) {
                $link_choice = $solr_field_settings['link_rendering'];
                $solr_field_settings['link_to_object'] = FALSE;
                $solr_field_settings['link_to_search'] = FALSE;
                if ($link_choice === 'object') {
                  $solr_field_settings['link_to_object'] = TRUE;
                }
                elseif ($link_choice === 'search') {
                  $solr_field_settings['link_to_search'] = TRUE;
                }
                unset($solr_field_settings['link_rendering']);
              }
            }
          }
          $current_values[] = [
            'solr_field' => $solr_field,
            'field_type' => $field_type,
            'weight' => $value['weight'],
            'solr_field_settings' => serialize($solr_field_settings),
          ];
        }
      }
    }
    // Get fields.
    $query = \Drupal::database()->select('islandora_solr_fields');
    $query->fields('islandora_solr_fields');
    $result = $query->execute();
    $records = $result->fetchAll(PDO::FETCH_ASSOC);
    // Find things to Add/Update.
    $insert_values = [];
    $update_values = [];
    foreach ($current_values as $current_value) {
      $found = FALSE;
      foreach ($records as $existing_value) {
        if ($current_value['solr_field'] == $existing_value['solr_field'] && $current_value['field_type'] == $existing_value['field_type']) {
          if ($current_value['weight'] != $existing_value['weight']) {
            $update_values[] = $current_value;
          }
          $found = TRUE;
          break;
        }
      }
      if (!$found) {
        $insert_values[] = $current_value;
      }
    }
    // Find things to remove.
    $remove_values = [
      'result_fields' => [],
      'sort_fields' => [],
      'facet_fields' => [],
      'search_fields' => [],
    ];
    foreach ($records as $existing_value) {
      $found = FALSE;
      foreach ($current_values as $current_value) {
        if ($current_value['solr_field'] == $existing_value['solr_field'] && $current_value['field_type'] == $existing_value['field_type']) {
          $found = TRUE;
          break;
        }
      }
      if (!$found) {
        $remove_values[$existing_value['field_type']] = $existing_value['solr_field'];
      }
    }
    // Remove values.
    foreach ($remove_values as $field_type => $values) {
      if (!$values) {
        break;
      }
      \Drupal::database()->delete('islandora_solr_fields')
        ->condition('field_type', $field_type)
        ->condition('solr_field', $values, 'IN')
        ->execute();
    }
    // Add values.
    if ($insert_values) {
      $insert = \Drupal::database()->insert('islandora_solr_fields')->fields([
        'solr_field',
        'field_type',
        'weight',
        'solr_field_settings',
      ]);
      foreach ($insert_values as $record) {
        $insert->values($record);
      }
      $insert->execute();
    }
    // Update values.
    foreach ($update_values as $value) {
      \Drupal::database()->update('islandora_solr_fields')
        ->fields(['weight' => $value['weight']])
        ->condition('field_type', $value['field_type'])
        ->condition('solr_field', $value['solr_field'])
        ->execute();
    }
    parent::submitForm($form, $form_state);
  }

}
