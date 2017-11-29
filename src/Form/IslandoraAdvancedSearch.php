<?php

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The advanced search form.
 */
class IslandoraAdvancedSearch extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_advanced_search_form';
  }

  /**
   * Islandora Solr advanced search block form.
   *
   * Check form states:
   * 1: Form update using AJAX.
   * 2: Populate with current query on search results page.
   * 3: Anywhere else: empty form.
   *
   * @link http://drupal.stackexchange.com/questions/14855/how-do-i-dynamically-fill-a-textfield-with-ajax/16576#16576 Some example AJAX. @endlink
   *
   * @global IslandoraSolrQueryProcessor $_islandora_solr_queryclass
   *   The IslandoraSolrQueryProcessor object which includes the current query
   *   settings and the raw Solr results.
   *
   * @param array $form
   *   An associative array containing form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An form state.
   *
   * @return array
   *   An associative array containing the fully built form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    global $_islandora_solr_queryclass;
    // @XXX: Drupal overwrites the form's default values after each AJAX
    // request; tracking an offset here so Drupal will uniquely identify
    // form elements across form builds.
    $storage = $form_state->getStorage();
    $storage['builds'] = isset($storage['builds']) ? $storage['builds'] + 1 : 1;
    $build = $storage['builds'];
    $form_state->setStorage($storage);

    // 1: Form update using AJAX.
    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element) {

      // Check for add.
      if ($triggering_element['#value'] == '+') {
        $temp_terms = $form_state->getValue('terms');
        $temp_terms[] = [];
        $form_state->setValue('terms', $temp_terms);
      }
      // Check for remove.
      elseif ($triggering_element['#value'] == '-') {
        $field = $triggering_element['#field'];
        $temp_terms = $form_state->getValue('terms');
        array_splice($temp_terms, $field, 1);
        $form_state->setValue('terms', $temp_terms);
      }

      $values = $form_state->getValues();
    }
    // 2: Populate with current query on search results page.
    elseif (islandora_solr_results_page($_islandora_solr_queryclass) == TRUE && !isset($_islandora_solr_queryclass->internalSolrParams['type'])) {

      // Get current query.
      $query = $_islandora_solr_queryclass->solrQuery;

      $values['terms'] = [];

      $query_explode = preg_split(ISLANDORA_SOLR_QUERY_SPLIT_REGEX, $query);

      // Break up the solr query to populate the advanced search form.
      $i = 0;
      foreach ($query_explode as $value) {
        $term = [];

        // Check for first colon to split the string.
        if (strpos($value, ':') != FALSE) {
          // Split the filter into field and value.
          $value_split = preg_split(ISLANDORA_SOLR_QUERY_FIELD_VALUE_SPLIT_REGEX, $value, 2);

          $values['terms'][$i]['field'] = stripslashes($value_split[0]);

          // Second part of the split is the query value (or first part of it).
          $value_split[1] = str_replace(['(', ')'], '', $value_split[1]);
          $values['terms'][$i]['search'] = stripslashes($value_split[1]);
        }
        // If the string does not include a colon or AND/OR/NOT, then it is a
        // part of the query value.
        elseif (!preg_match('/(AND|OR|NOT)/', $value, $matches)) {
          // Trim brackets.
          $value = str_replace(['(', ')'], '', $value);

          if (isset($values['terms'][$i]['search'])) {
            $values['terms'][$i]['search'] = $values['terms'][$i]['search'];
            // Append to search string.
            $values['terms'][$i]['search'] .= ' ' . stripslashes($value);
          }
          else {
            // Search field is not set, so create new search value.
            $values['terms'][$i]['search'] = stripslashes($value);
          }
        }
        // If it matches AND/OR/NOT, then we have the boolean operator.
        else {
          $values['terms'][$i]['boolean'] = $value;

          // XXX: Something about only incrementing here seems... Wrong?
          $i++;
        }
      }
    }
    // 3: Anywhere else: empty form.
    else {
      // Need at least one term to draw the search box.
      $values = [
        'terms' => [''],
      ];
    }

    $terms = [
      '#prefix' => '<div id="islandora-solr-advanced-terms">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];
    foreach ($values['terms'] as $i => $value_wrapper) {
      $value = isset($value_wrapper[$build - 1]) ? $value_wrapper[$build - 1] : [];
      $term = [
        '#tree' => TRUE,
        '#prefix' => '<div>',
        '#suffix' => '</div>',
      ];
      $term[$build]['field'] = [
        '#title' => $this->t('Field'),
        '#type' => 'select',
        '#default_value' => isset($value['field']) ? $value['field'] : 'dc.title',
        '#options' => islandora_solr_get_fields('search_fields'),
      ];
      $term[$build]['search'] = [
        '#title' => $this->t('Search terms'),
        '#type' => 'textfield',
        '#size' => 20,
        '#default_value' => (isset($value['search']) ?
          $value['search'] :
          ''),
      ];
      // Used for when the user presses enter on the search field.
      $term[$build]['hidden_submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Search'),
        '#attributes' => ['style' => 'visibility:hidden;position:fixed;top:-1000px;right:-1000px;'],
      ];
      $term[$build]['add'] = [
        '#type' => 'button',
        '#value' => '+',
        '#attributes' => ['title' => $this->t('Add field')],
        '#name' => 'add-field-' . $i,
        '#ajax' => [
          'callback' => '_islandora_solr_advanced_search_terms',
          'wrapper' => 'islandora-solr-advanced-terms',
          'method' => 'replace',
          'effect' => 'fade',
          'progress' => ['type' => 'none'],
        ],
      ];
      if (count($values['terms']) > 1) {
        $term[$build]['remove'] = [
          '#type' => 'button',
          '#field' => $i,
          '#value' => '-',
          '#attributes' => ['title' => $this->t('Remove field')],
          '#name' => 'remove-field-' . $i,
          '#ajax' => [
            'callback' => '_islandora_solr_advanced_search_terms',
            'wrapper' => 'islandora-solr-advanced-terms',
            'method' => 'replace',
            'effect' => 'fade',
            'progress' => ['type' => 'none'],
          ],
        ];
        if (($this->config('islandora_solr.settings')->get('islandora_solr_search_boolean') == 'user') && ((count($values['terms']) - 1) != $i)) {
          $term[$build]['boolean'] = [
            '#type' => 'select',
            '#prefix' => '<div>',
            '#suffix' => '</div>',
            '#default_value' => isset($value['boolean']) ? $value['boolean'] : 'AND',
            '#options' => [
              'AND' => 'AND',
              'OR' => 'OR',
              'NOT' => 'NOT',
            ],
          ];
        }
      }
      $terms[] = $term;
    }

    // Add terms.
    $form['terms'] = $terms;
    // Add controls.
    $form['controls'] = [
      '#type' => 'markup',
      '#prefix' => '<div class="islandora-solr-advanced-controls">',
      '#suffix' => '</div>',
    ];
    // Filter preservation toggle.
    if ($this->config('islandora_solr.settings')->get('islandora_solr_allow_preserve_filters')) {
      $form['controls']['islandora_solr_allow_preserve_filters'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Preserve Filters'),
        '#default_value' => FALSE,
      ];
    }
    $form['controls']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/utilities');
    $storage = $form_state->getStorage();
    $build = $storage['builds'];

    // Collect query values.
    $query_array = [];

    // Get Lucene syntax escaping configuration, prior to the following
    // foreach loop.
    $lucene_syntax_escape = $this->config('islandora_solr.settings')->get('islandora_solr_advanced_search_block_lucene_syntax_escape');
    $lucene_regex = $this->config('islandora_solr.settings')->get('islandora_solr_advanced_search_block_lucene_regex_default');

    foreach ($form_state->getValue('terms') as $term_wrapper) {
      $term = $term_wrapper[$build];
      $field = islandora_solr_lesser_escape($term['field']);
      $search = trim($term['search']);

      $boolean = (isset($term['boolean'])) ? $term['boolean'] : $this->config('islandora_solr.settings')->get('islandora_solr_search_boolean');

      // Add query.
      if (!empty($search)) {
        $search = $lucene_syntax_escape ?
          islandora_solr_facet_query_escape($search, $lucene_regex) :
          islandora_solr_lesser_escape($search);

        $query_array[] = [
          'search' => "$field:($search)",
          'boolean' => $boolean,
        ];
      }
    }

    // Create query.
    $query = '';
    $i = 0;
    foreach ($query_array as $term) {
      $query .= $term['search'];
      if (count($query_array) - 1 != $i) {
        $query .= ' ' . $term['boolean'] . ' ';
      }
      $i++;
    }

    // Check if query is empty.
    if (empty($query)) {
      if ($this->config('islandora_solr.settings')->get('islandora_solr_base_advanced')) {
        $query = $this->config('islandora_solr.settings')->get('islandora_solr_base_query');
      }
      else {
        $query = '*:*';
      }
    }

    // Handle filters.
    $filter = '';
    if ($form_state->getValue('islandora_solr_allow_preserve_filters')) {
      $filter = isset($_GET['f']) ? $_GET['f'] : '';
    }

    // Use work around for some special URL characters.
    $query = islandora_solr_replace_slashes($query);

    // Navigate to results page.
    $form_state->setRedirect(
      'islandora_solr.islandora_solr',
      ['query' => $query],
      $filter ? ['query' => ['f' => $filter]] : []
    );
  }

}
