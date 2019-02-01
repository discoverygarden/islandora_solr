<?php

namespace Drupal\islandora_solr;

use Drupal\Core\Url;
use Drupal\Core\Template\Attribute;

use Drupal\Component\Utility\Html;

/**
 * Islandora Solr Results.
 */
class IslandoraSolrResults {

  public $facetFieldArray = [];
  public $searchFieldArray = [];
  public $resultFieldArray = [];
  public $allSubsArray = [];
  public $islandoraSolrQueryProcessor;
  public $rangeFacets = [];
  public $dateFormatFacets = [];

  /**
   * Constructor.
   */
  public function __construct() {
    $this->prepFieldSubstitutions();
    $this->rangeFacets = islandora_solr_get_range_facets();
    $this->dateFormatFacets = islandora_solr_get_date_format_facets();
  }

  /**
   * Output the main body of the search results.
   *
   * @param IslandoraSolrQueryProcessor $islandora_solr_query
   *   The IslandoraSolrQueryProcessor object which includes the current query
   *   settings and the raw Solr results.
   *
   * @return string
   *   Returns themed Solr results page, including wrapper and rendered search
   *   results.
   *
   * @see islandora_solr()
   */
  public function displayResults(IslandoraSolrQueryProcessor $islandora_solr_query) {
    $this->islandoraSolrQueryProcessor = $islandora_solr_query;

    // Set variables to collect returned data.
    $results = NULL;
    $secondary_profiles = NULL;
    $elements = [];

    // Raw solr results.
    $islandora_solr_result = $this->islandoraSolrQueryProcessor->islandoraSolrResult;

    // Solr results count.
    // Total Solr results.
    $elements['solr_total'] = (int) $islandora_solr_result['response']['numFound'];

    // Solr start.
    // To display: $islandora_solr_query->solrStart + ($total > 0 ? 1 : 0).
    $elements['solr_start'] = $islandora_solr_query->solrStart;

    // Solr results end.
    $end = min(($islandora_solr_query->solrLimit + $elements['solr_start']), $elements['solr_total']);
    $elements['solr_end'] = $end;

    // Pager.
    islandora_solr_pager_init($elements['solr_total'], $islandora_solr_query->solrLimit);
    $elements['solr_pager'] = [
      '#type' => 'pager',
      '#element' => 0,
      '#quantity' => 5,
    ];

    // Debug (will be removed).
    $elements['solr_debug'] = '';
    if (\Drupal::config('islandora_solr.settings')->get('islandora_solr_debug_mode') && \Drupal::currentUser()->hasPermission('view islandora solr debug')) {
      $elements['solr_debug'] = $this->printDebugOutput($islandora_solr_result);
    }

    // Rendered secondary display profiles.
    $secondary_profiles = $this->addSecondaries($islandora_solr_query);

    // Rendered results.
    $results = $this->printResults($islandora_solr_result);

    return [
      '#theme' => 'islandora_solr_wrapper',
      '#results' => $results,
      '#secondary_profiles' => $secondary_profiles,
      '#elements' => $elements,
    ];
  }

  /**
   * Renders the secondary display profile list.
   *
   * @param IslandoraSolrQueryProcessor $islandora_solr_query
   *   The IslandoraSolrQueryProcessor object which includes the current query
   *   settings and the raw Solr results.
   *
   * @return array
   *   Themed list of secondary displays
   *
   * @see IslandoraSolrResults::displayResults()
   */
  public function addSecondaries(IslandoraSolrQueryProcessor $islandora_solr_query) {
    $query_list = [];
    // Get secondary display profiles.
    $secondary_display_profiles = \Drupal::moduleHandler()->invokeAll('islandora_solr_secondary_display');

    // Parameters set in URL.
    $params = $islandora_solr_query->internalSolrParams;

    // Get list of secondary displays.
    $secondary_array = \Drupal::config('islandora_solr.settings')->get('islandora_solr_secondary_display');
    foreach ($secondary_array as $name => $status) {
      if ($status === $name) {
        // Generate URL.
        $query_secondary = array_merge($params, ['solr_profile' => $name]);

        // Set attributes variable for remove link.
        $attr = new Attribute();
        $attr['title'] = $secondary_display_profiles[$name]['description'];
        $attr['rel'] = 'nofollow';
        $attr['href'] = Url::fromRoute('<current>', [], ['query' => $query_secondary])->toString();

        $logo = $secondary_display_profiles[$name]['logo'];

        // XXX: We are not using l() because of active classes:
        // @see http://drupal.org/node/41595
        // Create link.
        $query_list[]['#markup'] = '<a' . $attr . '>' . $logo . '</a>';
      }
    }
    return [
      '#theme' => 'item_list',
      '#items' => $query_list,
      '#title' => NULL,
      '#type' => 'ul',
      '#attributes' => ['id' => 'secondary-display-profiles'],
    ];

  }

  /**
   * Renders the primary or secondary display profile.
   *
   * Renders the primary or secondary display profile based on the raw Solr
   * results. This is the method most Islandora Solr display plugins would
   * override.
   *
   * @param array $solr_results
   *   The raw Solr results from
   *   IslandoraSolrQueryProcessor::$islandoraSolrResult.
   *
   * @return string
   *   Rendered Solr results
   *
   * @see IslandoraSolrResults::displayResults()
   */
  public function printResults(array $solr_results) {
    module_load_include('inc', 'islandora_solr', 'includes/db');
    $solr_results = islandora_solr_prepare_solr_results($solr_results);
    $object_results = $solr_results['response']['objects'];
    $object_results = islandora_solr_prepare_solr_doc($object_results);

    $elements = [];
    $elements['solr_total'] = $solr_results['response']['numFound'];
    $elements['solr_start'] = $solr_results['response']['start'];

    $return = [
      '#theme' => 'islandora_solr',
      '#results' => $object_results,
      '#elements' => $elements,
    ];
    if (islandora_solr_get_truncate_length_fields()) {
      $return['#attached']['library'][] = 'islandora_solr/toggle';
    }
    // Return themed search results.
    return $return;
  }

  /**
   * Displays elements of the current solr query.
   *
   * Displays current query and current filters. Includes a link to exclude the
   * query/filter.
   *
   * @param IslandoraSolrQueryProcessor $islandora_solr_query
   *   The IslandoraSolrQueryProcessor object which includes the current query
   *   settings and the raw Solr results.
   *
   * @return string
   *   Rendered lists of the currently active query and/or filters.
   */
  public function currentQuery(IslandoraSolrQueryProcessor $islandora_solr_query) {
    module_load_include('inc', 'islandora', 'includes/utilities');
    $output = '';
    $path = Url::fromRoute("<current>")->toString();

    // Get user provided filter parameters.
    $fq = isset($islandora_solr_query->internalSolrParams['f']) ? $islandora_solr_query->internalSolrParams['f'] : [];
    // Parameters set in URL.
    $params = $islandora_solr_query->internalSolrParams;
    // Get query values.
    if (!in_array($islandora_solr_query->solrQuery, $islandora_solr_query->differentKindsOfNothing)) {
      // Get query value.
      $query_value = stripslashes($islandora_solr_query->solrQuery);

      $query_list = [];
      if (\Drupal::config('islandora_solr.settings')->get('islandora_solr_human_friendly_query_block')) {
        foreach ($this->searchFieldArray as $search_field => $search_field_label) {
          $query_value = str_replace($search_field . ':(', $search_field_label . ':(', $query_value);
        }
      }

      // Remove link keeps all parameters (query gets removed instead).
      $query_minus = $params;

      // Set attributes variable for remove link.
      $attributes = [
        'minus' => [
          'path' => $path,
          'query' => $query_minus,
        ],
      ];
      $attr_minus = new Attribute();
      $attr_minus['title'] = t('Remove') . ' ' . $query_value;
      $attr_minus['class'] = ['remove-query'];
      $attr_minus['rel'] = 'nofollow';
      $attr_minus['href'] = Url::fromRoute('islandora_solr.islandora_solr', [], ['query' => $query_minus])->toString();
      $attributes['minus']['attr'] = $attr_minus;

      $hooks = islandora_build_hook_list(ISLANDORA_SOLR_FACET_BUCKET_CLASSES_HOOK_BASE);
      \Drupal::moduleHandler()->alter($hooks, $attributes, $islandora_solr_query);
      // XXX: We are not using l() because of active classes:
      // @see http://drupal.org/node/41595
      // Create link.
      $query_list[]['#markup'] = '<a' . $attr_minus . '>(-)</a> ' . Html::escape($query_value);

      // Add wrap and list.
      $output .= '<div class="islandora-solr-query-wrap">';
      $list = [
        '#theme' => 'item_list',
        '#items' => $query_list,
        '#title' => t('Query'),
        '#type' => 'ul',
        '#attributes' => ['class' => ['islandora-solr-query-list', 'query-list']],
      ];
      $output .= \Drupal::service('renderer')->render($list);

      $output .= '</div>';

    }

    $filter_list = [];
    // Get filter values.
    if (!empty($fq)) {
      // Set list variables.
      foreach ($fq as $filter) {
        // Check for exclude filter.
        if ($filter[0] == '-') {
          // Not equal sign.
          $symbol = '&ne;';
        }
        else {
          $symbol = '=';
        }
        $filter_string = $this->formatFilter($filter, $islandora_solr_query);
        // Pull out filter (for exclude link).
        $query_minus = [];
        $f_x['f'] = array_diff($params['f'], [$filter]);
        $query_minus = array_merge($params, $f_x);
        // @todo Find a cleaner way to do this.
        // Resetting the filter keys' order.
        if ($query_minus['f']) {
          $query_minus['f'] = array_merge([], $query_minus['f']);
        }
        // Remove 'f' if empty.
        if (empty($query_minus['f'])) {
          unset($query_minus['f']);
        }
        // Set attributes variable for remove link.
        $attributes = [
          'minus' => [
            'attr' => [],
            'path' => $path,
            'query' => $query_minus,
          ],
        ];
        $attr_minus = new Attribute();
        $attr_minus['title'] = t('Remove') . ' ' . $filter;
        $attr_minus['class'] = ['remove-filter'];
        $attr_minus['rel'] = 'nofollow';
        $attr_minus['href'] = Url::fromRoute('islandora_solr.islandora_solr', [], ['query' => $query_minus])->toString();
        $attributes['minus']['attr'] = $attr_minus;

        $hooks = islandora_build_hook_list(ISLANDORA_SOLR_FACET_BUCKET_CLASSES_HOOK_BASE);
        \Drupal::moduleHandler()->alter($hooks, $attributes, $islandora_solr_query);
        // XXX: We are not using l() because of active classes:
        // @see http://drupal.org/node/41595
        // Create link.
        $filter_list[]['#markup'] = '<a' . $attr_minus . '>(-)</a> ' . $symbol . ' ' . Html::escape($filter_string);
      }

      // Return filter list.
      $output .= '<div class="islandora-solr-filter-wrap">';
      $list = [
        '#theme' => 'item_list',
        '#items' => $filter_list,
        '#title' => t("Enabled Filters"),
        '#type' => 'ul',
        '#attributes' => ['class' => ['islandora-solr-filter-list', 'filter-list']],
      ];
      $output .= \Drupal::service('renderer')->render($list);

      $output .= '</div>';
    }
    return $output;
  }

  /**
   * Formats the passed in filter into a human readable form.
   *
   * @param string $filter
   *   The passed in filter.
   * @param object $islandora_solr_query
   *   The current Solr Query.
   *
   * @return string
   *   The formatted filter string for breadcrumbs and active query.
   */
  public function formatFilter($filter, $islandora_solr_query) {
    // @todo See how this interacts with multiple date filters.
    // Check if there are operators in the filter.
    $fq_split = preg_split('/ (OR|AND) /', $filter);
    if (count($fq_split) > 1) {
      $operator_split = preg_split(ISLANDORA_SOLR_QUERY_SPLIT_REGEX, $filter);
      $operator_split = array_diff($operator_split, $fq_split);
      $out_array = [];
      foreach ($fq_split as $fil) {
        $fil_split = preg_split(ISLANDORA_SOLR_QUERY_FIELD_VALUE_SPLIT_REGEX, $fil, 2);
        $out_str = str_replace(['"', 'info:fedora/'], '', $fil_split[1]);
        $out_array[] = $out_str;
      }
      $filter_string = '';
      foreach ($out_array as $out) {
        $filter_string .= $out;
        if (count($operator_split)) {
          $filter_string .= ' ' . array_shift($operator_split) . ' ';
        }
      }
      $filter_string = trim($filter_string);
    }
    else {
      // Split the filter into field and value.
      $filter_split = preg_split(ISLANDORA_SOLR_QUERY_FIELD_VALUE_SPLIT_REGEX, $filter, 2);
      // Trim brackets.
      $filter_split[1] = trim($filter_split[1], "\"");
      $solr_field = ltrim($filter_split[0], '-');
      // If value is date.
      if (isset($islandora_solr_query->solrParams['facet.date']) && in_array($solr_field, $islandora_solr_query->solrParams['facet.date'])) {
        // Check date format setting.
        foreach ($this->rangeFacets as $value) {
          if ($value['solr_field'] == $solr_field && isset($value['solr_field_settings']['date_facet_format']) && !empty($value['solr_field_settings']['date_facet_format'])) {
            $format = $value['solr_field_settings']['date_facet_format'];
          }
        }
        // Split range filter string to return formatted date values.
        $filter_str = $filter_split[1];
        $filter_str = trim($filter_str, '[');
        $filter_str = trim($filter_str, ']');
        $filter_array = explode(' TO ', $filter_str);
        $filter_split[1] = format_date(strtotime(trim($filter_array[0])) + (60 * 60 * 24), 'custom', $format) . ' - ' . format_date(strtotime(trim($filter_array[1])) + (60 * 60 * 24), 'custom', $format);
      }
      elseif (isset($this->dateFormatFacets[$solr_field])) {
        $format = $this->dateFormatFacets[$solr_field]['solr_field_settings']['date_facet_format'];
        $filter_split[1] = format_date(strtotime(stripslashes($filter_split[1])), 'custom', $format);
      }
      $filter_string = $filter_split[1];
    }
    return stripslashes($filter_string);
  }

  /**
   * Displays facets based on a query response.
   *
   * Includes links to include or exclude a facet field in a search.
   *
   * @param IslandoraSolrQueryProcessor $islandora_solr_query
   *   The IslandoraSolrQueryProcessor object which includes the current query
   *   settings and the raw Solr results.
   *
   * @return string
   *   Rendered lists of facets including links to include or exclude a facet
   *   field.
   *
   * @see islandora_solr_islandora_solr_query_blocks()
   * @see islandora_solr_block_view()
   */
  public function displayFacets(IslandoraSolrQueryProcessor $islandora_solr_query) {
    IslandoraSolrFacets::init($islandora_solr_query);
    $output = [
      '#attached' => [
        'library' => ['islandora_solr/facets-js'],
      ],
    ];
    $facet_order = $this->facetFieldArray;
    foreach ($facet_order as $facet_key => $facet_label) {
      $facet_obj = new IslandoraSolrFacets($facet_key);
      $output[$facet_key] = $facet_obj->getFacet();
    }
    return $output;
  }

  /**
   * Create a fieldset for debugging purposes.
   *
   * Creates a fieldset containing raw Solr results of the current page for
   * debugging purposes.
   *
   * @param array $islandora_solr_results
   *   The processed Solr results from
   *   IslandoraSolrQueryProcessor::islandoraSolrResult.
   *
   * @return string
   *   Rendered fieldset containing raw Solr results data.
   *
   * @see IslandoraSolrResults::displayResults()
   */
  public function printDebugOutput(array $islandora_solr_results) {
    // Debug dump.
    $results = "<pre>Results: " . print_r($islandora_solr_results, TRUE) . "</pre>";
    $fieldset = [
      '#title' => t("Islandora Processed Solr Results"),
      '#type' => 'details',
      '#open' => TRUE,
      '#markup' => $results,
      '#children' => '',
    ];
    return \Drupal::service('renderer')->render($fieldset);
  }

  /**
   * Reads configuration values and prepares field => label mappings.
   *
   * Reads configuration values and preps a number of key => value arrays for
   * output substitution. Replaces solr field labels with human readable labels
   * as set in the admin form.
   */
  public function prepFieldSubstitutions() {

    $this->facetFieldArray = islandora_solr_get_fields('facet_fields');

    $this->searchFieldArray = islandora_solr_get_fields('search_fields');

    $this->resultFieldArray = islandora_solr_get_fields('result_fields');

    $this->allSubsArray = array_merge($this->facetFieldArray, $this->searchFieldArray, $this->resultFieldArray);
  }

}
