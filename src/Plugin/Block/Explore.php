<?php

namespace Drupal\islandora_solr\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormStateInterface;

use Drupal\islandora\Plugin\Block\AbstractConfiguredBlockBase;

/**
 * Provides a block for exploring objects through facets.
 *
 * @Block(
 *   id = "islandora_solr_explore",
 *   admin_label = @Translation("Islandora explore"),
 * )
 */
class Explore extends AbstractConfiguredBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    module_load_include('inc', 'islandora_solr', 'includes/explore');
    return _islandora_solr_explore_generate_links();
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'search islandora solr');
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/blocks');
    $form = parent::blockForm($form, $form_state);

    // Get the variables for the form display facets.
    $explore_config = ($form_state->get('islandora_solr_facet_filters') ?
      $form_state->get('islandora_solr_facet_filters') :
      $this->configFactory->get('islandora_solr.settings')->get('islandora_solr_explore_config'));

    $triggering_element = $form_state->getTriggeringElement();
    // Check if remove was clicked and removed the label and filter from the
    // values and the table.
    if ($triggering_element && $triggering_element['#id'] == 'facet-filter-remove') {
      foreach ($form_state->getCompleteFormState()->getValue([
        'settings',
        'facet',
        'table',
      ]) as $key => $row) {
        if (!empty($row)) {
          // Get selected row index.
          $row_index = str_replace("facet-row-", "", $key);
          // Unset the index to keep the other keys the same.
          unset($explore_config[$row_index]);
        }
      }
      $form_state->set('islandora_solr_facet_filters', $explore_config);
    }

    // Check if weights are being updated.
    if ($triggering_element && $triggering_element['#id'] == 'facet-filter-weight' && !empty($explore_config)) {
      // Note: select_weight is only in the $form_state['input'] and doesn't
      // exist in $form_state['values'].
      $selected_weights = $form_state->get(['input', 'select_weight']);
      foreach ($selected_weights as $index => $weight) {
        $explore_config[$index]['weight'] = $weight;
      }
      // Sort config array by weight and update drupal variable.
      uasort($explore_config, 'drupal_sort_weight');
      $form_state->set('islandora_solr_facet_filters', $explore_config);
    }

    // Check if new display facet is being added to the table.
    if ($triggering_element && $triggering_element['#id'] == 'facet-filter-add-more') {
      $duplicate_label = FALSE;
      $duplicate_filter = FALSE;
      $duplicate = FALSE;
      $facet_label = $form_state->getCompleteFormState()->getValue(['settings',
        'facet',
        'fieldset',
        'label',
      ]);
      $facet_filter = $form_state->getCompleteFormState()->getValue(['settings',
        'facet',
        'fieldset',
        'filter',
      ]);
      $facet_weight = $form_state->getCompleteFormState()->getValue(['settings',
        'facet',
        'fieldset',
        'facet_weight',
      ]);

      if (!empty($facet_label) && !empty($explore_config)) {
        foreach ($explore_config as $row) {
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
          $duplicate = TRUE;
        }
        elseif ($duplicate_filter) {
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
        }

        if ($facet_is_valid) {
          // Put values into config array.
          $explore_config[$facet_filter] = [
            'label' => $facet_label,
            'filter' => $facet_filter,
            'weight' => $facet_weight,
          ];

          // Sort config array by weight and update drupal variable.
          uasort($explore_config, ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);

          // Reset input values on successful add.
          $form_state->set(['input', 'label'], '');
          $form_state->set(['input', 'filter'], '');
          $form_state->set(['input', 'facet_weight'], 0);
          $form_state->set(['islandora_solr_facet_filters'], $explore_config);
        }
      }
    }

    // Set table row data to be rendered.
    $rows = [];
    if (!empty($explore_config)) {
      foreach ($explore_config as $row_index => $row) {
        $rows["facet-row-$row_index"] = [
          'display_label' => htmlentities($row['label']),
          'facet_filter' => htmlentities($row['filter']),
          'facet_weight' => [
            'data' => [
              '#type' => 'select',
              '#title' => $this->t('Weight'),
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
      '#title' => $this->t('Setup Display Facets'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];
    $form['facet']['table'] = [
      '#type' => 'tableselect',
      '#header' => [
        'display_label' => $this->t('Display Label'),
        'facet_filter' => $this->t('Facet Query Filter'),
        'facet_weight' => $this->t('Weight'),
      ],
      '#options' => $rows,
      '#empty' => $this->t('No display facets'),
      '#attributes' => ['class' => ['main-facet-table']],
    ];
    $form['facet']['actions'] = [
      '#type' => 'actions',
      '#weight' => 5,
    ];
    $form['facet']['actions']['remove'] = [
      '#type' => 'button',
      '#value' => $this->t('Remove selected'),
      '#id' => 'facet-filter-remove',
      '#weight' => 0,
      '#ajax' => [
        'callback' => '_islandora_solr_update_filter_table',
        'wrapper' => 'islandora-solr-facet-filter-wrapper',
      ],
    ];
    $form['facet']['actions']['update_weight'] = [
      '#type' => 'button',
      '#value' => $this->t('Update weights'),
      '#weight' => 1,
      '#id' => 'facet-filter-weight',
      '#ajax' => [
        'callback' => '_islandora_solr_update_filter_table',
        'wrapper' => 'islandora-solr-facet-filter-wrapper',
      ],
    ];
    $form['facet']['fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Add New Display Facet Details'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#weight' => 13,
    ];
    $form['facet']['fieldset']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display Label'),
      '#size' => 60,
      '#description' => $this->t('The text displayed for the generated link.'),
      '#attributes' => ['class' => ['new-facet-label']],
    ];
    $form['facet']['fieldset']['filter'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Facet Query Filter'),
      '#size' => 60,
      '#description' => $this->t('Write a custom facet query, for information on available tags see your Solr admin.'),
      '#attributes' => ['class' => ['new-facet-filter']],
    ];

    $form['facet']['fieldset']['facet_weight'] = [
      '#type' => 'select',
      '#title' => $this->t('Facet weight'),
      '#description' => $this->t('Display weight'),
      '#options' => array_combine(range(-50, 50), range(-50, 50)),
      '#default_value' => 0,
      '#attributes' => ['class' => ['new-facet-weight']],
    ];
    $form['facet']['fieldset']['add'] = [
      '#type' => 'button',
      '#value' => $this->t('Add'),
      '#attributes' => ['class' => ['islandora-solr-add-more-submit']],
      '#id' => 'facet-filter-add-more',
      '#ajax' => [
        'callback' => '_islandora_solr_update_filter_table',
        'wrapper' => 'islandora-solr-facet-filter-wrapper',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#id'] != 'facet-filter-add-more') {
      return;
    }
    $explore_config = ($form_state->get('islandora_solr_facet_filters') ? $form_state->get('islandora_solr_facet_filters') : $this->configFactory->get('islandora_solr.settings')->get('islandora_solr_explore_config'));
    $facet_label = $form_state->getCompleteFormState()->getValue(['settings',
      'facet',
      'fieldset',
      'label',
    ]);
    $facet_filter = $form_state->getCompleteFormState()->getValue(['settings',
      'facet',
      'fieldset',
      'filter',
    ]);
    $duplicate = FALSE;

    if (empty($facet_filter)) {
      $form_state->setErrorByName('filter', $this->t('Facet Filter is required to add a display facet.'));
    }

    if (empty($facet_label)) {
      $form_state->setErrorByName('label', $this->t('Display Label is required to add a display facet.'));
    }
    if (!empty($facet_label) && !empty($explore_config)) {
      foreach ($explore_config as $row) {
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
        $form_state->setErrorByName('label', $this->t('Display Label must be unique.'));
        $duplicate = TRUE;
      }
      elseif ($duplicate_filter) {
        $form_state->setErrorByName('filter', $this->t('Facet Filter must be unique.'));
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
          $form_state->setErrorByName('filter', $this->t('Invalid Facet Query Filter.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('islandora_solr.settings');

    if ($form_state->get('islandora_solr_facet_filters')) {
      $config->set('islandora_solr_explore_config', $form_state->get('islandora_solr_facet_filters'));
    }
    else {
      $config->delete('islandora_solr_explore_config');
    }

    $config->save();
  }

}
