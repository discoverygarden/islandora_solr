<?php

namespace Drupal\islandora_solr;

use Drupal\Core\Url;
use Drupal\Core\Template\Attribute;

use Drupal\islandora_solr\Form\IslandoraDateFilter;
use Drupal\islandora_solr\Form\IslandoraRangeSlider;

/**
 * Islandora Solr Facets.
 */
class IslandoraSolrFacets {
  public static $islandoraSolrQuery;

  // XXX: Need to fix the property/member variable names...  Could have an
  // effect on other code, though, due to public visibility.
  // @codingStandardsIgnoreStart
  // Normal facet results.
  public static $facet_fields;
  // Date range facet results (Solr 1.4).
  public static $facet_dates;
  // Date or integer range facet results (Solr 3.1).
  public static $facet_ranges;

  public static $facet_fields_settings;
  public static $facet_fields_settings_simple;
  // Rename?
  public static $range_facets;
  public static $minimum_count = 1;
  public static $soft_limit;
  public static $exclude_range_values;
  public static $needed_solr_call;
  public static $range_slider_key = 0;
  public static $date_filter_key = 0;

  public $facet_field;
  public $settings;
  public $facet_type;
  public $results;
  public $title = NULL;
  public $content = [];

  /**
   * Is this a range on a Solr date field?
   *
   * @var bool
   *   Whether it is.
   */
  protected $date_range = FALSE;

  protected $sliderKey = NULL;


  // @codingStandardsIgnoreEnd

  /**
   * Constructor method.
   *
   * Stores the facet field name, settings and title in a parameter.
   *
   * @param string $facet_field
   *   The name of the solr field to build a facet for.
   */
  public function __construct($facet_field) {
    $this->facet_field = $facet_field;
    $this->settings = self::$facet_fields_settings[$facet_field];
    $this->title = self::$facet_fields_settings_simple[$facet_field];
  }

  /**
   * Static init.
   *
   * Populates static variables with Solr query results and user settings.
   *
   * @param object $islandora_solr_query
   *   Object containing the Solr query and results.
   */
  public static function init($islandora_solr_query) {
    self::$islandoraSolrQuery = $islandora_solr_query;
    self::$facet_fields = isset($islandora_solr_query->islandoraSolrResult) ? $islandora_solr_query->islandoraSolrResult['facet_counts']['facet_fields'] : [];
    // XXX: isset() checking, as newer Solrs (7) won't return a value.
    self::$facet_dates = isset($islandora_solr_query->islandoraSolrResult['facet_counts']['facet_dates']) ? $islandora_solr_query->islandoraSolrResult['facet_counts']['facet_dates'] : [];
    // XXX: isset() checking, as older Solrs (before 3.1) won't return a value.
    self::$facet_ranges = isset($islandora_solr_query->islandoraSolrResult['facet_counts']['facet_ranges']) ?
      $islandora_solr_query->islandoraSolrResult['facet_counts']['facet_ranges'] :
      [];

    // Filtered, not simplified and fields as keys.
    self::$facet_fields_settings = islandora_solr_get_fields('facet_fields', TRUE, FALSE, TRUE);
    self::$facet_fields_settings_simple = _islandora_solr_simplify_fields(self::$facet_fields_settings);
    self::$range_facets = islandora_solr_get_range_facets();
    self::$soft_limit = \Drupal::config('islandora_solr.settings')->get('islandora_solr_facet_soft_limit');
    self::$exclude_range_values = [
      'gap',
      'start',
      'end',
      'other',
      'hardend',
      'include',
    ];

    // Calculate variable date gap.
    // @XXX move elsewhere?
    self::variableDateGap();
  }

  /**
   * Prepare and render facet.
   *
   * Method called after a facet object is created. This will prepare the
   * results based on the type and user settings for this facet. It also does a
   * call to render the prepared data. This method also returns the rendered
   * endresult.
   *
   * @return string
   *   Returns the title and rendered facet.
   */
  public function getFacet() {
    $this->findFacetType();
    $this->getFacetResults();
    if (empty($this->results)) {
      return [];
    }
    $this->processFacets();
    if (empty($this->content)) {
      return [];
    }
    return [
      '#prefix' => '<div class="islandora-solr-facet-wrapper"><h3>' . $this->title . '</h3>',
      'content' => $this->content,
      '#suffix' => '</div>',
    ];
  }

  /**
   * Check facet type.
   *
   * Depending on facet type and Solr version, Solr will return different types
   * of facets in different arrays in the results object. Itereate where the
   * current facet object has returned.
   */
  public function findFacetType() {
    $facet_field = $this->facet_field;
    if (array_key_exists($facet_field, self::$facet_fields)) {
      $this->facet_type = 'facet_fields';
    }
    if (array_key_exists($facet_field, self::$facet_dates)) {
      $this->facet_type = 'facet_dates';
    }
    if (array_key_exists($facet_field, self::$facet_ranges)) {
      $this->facet_type = 'facet_ranges';
      $this->date_range = islandora_solr_is_date_field($facet_field);
    }
  }

  /**
   * Gets facet results.
   *
   * Finds and stores facet results from the Solr query into a property.
   */
  public function getFacetResults() {
    $facet_field = $this->facet_field;
    $facet_type = $this->facet_type;
    if ($facet_type == 'facet_fields') {
      $this->results = self::$facet_fields[$facet_field];
    }
    if ($facet_type == 'facet_dates') {
      $this->results = self::$facet_dates[$facet_field];
    }
    if ($facet_type == 'facet_ranges') {
      $this->results = self::$facet_ranges[$facet_field];
    }
  }

  /**
   * Process facets.
   *
   * Calls a process method based on the facet type. This method exists so this
   * process of preparing and rendering arrays can be started from one simple
   * method call.
   */
  public function processFacets() {
    $facet_type = $this->facet_type;
    if ($facet_type == 'facet_fields') {
      $this->processFacetFields();
    }
    if ($facet_type == 'facet_dates'
      || ($facet_type == 'facet_ranges' && $this->date_range)) {
      $this->processFacetDates();
    }
    if ($facet_type == 'facet_ranges') {
      $this->processFacetRanges();
    }
  }

  /**
   * Process facet fields.
   *
   * Calls prepare method to prepare the array to be rendered. Then calls
   * the method to render the prepared results.
   */
  public function processFacetFields() {
    $prepared_facet_fields = $this->prepareFacetFields();
    $this->renderText($prepared_facet_fields);
  }

  /**
   * Process facet dates.
   *
   * Depending on the way the facets need to be rendered as set in the user
   * configuration, this method calls methods to prepare the results to be
   * rendered. Then calls the method to render the prepared results. Currently
   * there are 3 ways facet dates can be rendered: normal text, slider and/or
   * datepicker.
   *
   * @todo Maybe there should be some better pluggable way to prepare and
   *   render facets so that contrib modules can create their own widgets.
   */
  public function processFacetDates() {
    // @todo Make this check better. Could be pluggable in the future.
    if (isset($this->settings['range_facet_slider_enabled']) &&
        $this->settings['range_facet_slider_enabled']) {
      // Prepare and render facet dates as slider.
      $facet_dates_as_slider = $this->prepareFacetDatesSlider();
      if (!empty($facet_dates_as_slider)) {
        $this->renderSlider($facet_dates_as_slider);
      }
    }
    else {
      // Prepare and render facet dates as text.
      $facet_dates_as_text = $this->prepareFacetDates();
      $this->renderText($facet_dates_as_text);
    }
    // Date filter.
    if (isset($this->settings['date_filter_datepicker_enabled']) &&
        $this->settings['date_filter_datepicker_enabled'] == 1) {
      // Prepare and render facet dates as slider.
      $facet_dates_datefilter = $this->prepareFacetDatesFilter();
      if (!empty($facet_dates_datefilter)) {
        $this->renderFacetDatesFilter($facet_dates_datefilter);
      }
    }
  }

  /**
   * Process facet ranges.
   *
   * Not in place yet.
   */
  public function processFacetRanges() {

  }

  /**
   * Get date format.
   *
   * Finds the date formatting settings from user configuration.
   */
  public function getDateFormat() {
    if (isset($this->settings['date_facet_format']) &&
        !empty($this->settings['date_facet_format'])) {
      return $this->settings['date_facet_format'];
    }
    else {
      return 'Y';
    }
  }

  /**
   * Prepare facet fields for text rendering.
   */
  public function prepareFacetFields() {
    $results = $this->results;
    $facet_field = $this->facet_field;
    $facet_results = [];
    module_load_include('inc', 'islandora_solr', 'includes/utilities');
    // It's possible that there could be a facet that's a date field
    // that's not a range.
    $date_format = isset($this->settings['date_facet_format']) ? $this->settings['date_facet_format'] : FALSE;
    foreach ($results as $bucket => $count) {
      $facet_results[] = [
        'count' => $count,
        'filter' => islandora_solr_lesser_escape($facet_field) . ':"' .
        islandora_solr_facet_escape($bucket) . '"',
        'bucket' => $date_format ? format_date(strtotime($bucket), 'custom', $date_format) : $bucket,
      ];
    }
    return $facet_results;
  }

  /**
   * Prepare facet dates for text rendering.
   */
  public function prepareFacetDates() {
    $facet_field = $this->facet_field;
    $results = $this->results;
    $format = $this->getDateFormat();
    if ($this->facet_type == 'facet_ranges') {
      $results = array_merge($results, $results['counts']);
      unset($results['counts']);
    }
    $date_results = [];
    // Render date facet fields.
    foreach ($results as $bucket => $count) {
      // Don't include gap, end, etc that comes with range results.
      if (in_array($bucket, self::$exclude_range_values)) {
        continue;
      }
      $item = [];
      // Set count or documents.
      $item['count'] = $count;
      // Logic to get the next range key (next date).
      $field_keys = array_keys($results);
      $field_key = array_search($bucket, $field_keys);
      $field_key++;
      $bucket_next = (isset($field_keys[$field_key]) && !in_array($field_keys[$field_key], self::$exclude_range_values) ? $field_keys[$field_key] : $results['end']);
      // Set date range filter for facet URL.
      $item['filter'] = $facet_field . ':[' . $bucket . ' TO ' . $bucket_next . ']';
      // Set formatted value for facet link.
      $item['bucket'] = format_date(strtotime($bucket) + (60 * 60 * 24), 'custom', $format) . ' - ' . format_date(strtotime($bucket_next) + (60 * 60 * 24), 'custom', $format);
      $date_results[] = $item;
    }
    return $date_results;
  }

  /**
   * Render text facets.
   *
   * Based on a prepared array of results, this method will process and render
   * a facet as normal text. It includes bucket value, count, include link and
   * exclude link. If configured it also adds a 'read more' link to expose more
   * results.
   *
   * @param array $results
   *   An array with the prepared facet results.
   */
  public function renderText(array $results) {
    $facet_field = $this->facet_field;
    $islandora_solr_query = self::$islandoraSolrQuery;
    $soft_limit = self::$soft_limit;
    $buckets = [];
    $replace_bucket = (isset($this->settings['pid_object_label']) && $this->settings['pid_object_label'] ? TRUE : FALSE);
    $pid_mapper = function ($result) {
      $pid = str_replace('info:fedora/', '', $result['bucket']);
      return $pid;
    };
    $labels = [];
    if ($replace_bucket) {
      $valid_pids = array_filter(array_map($pid_mapper, $results), 'islandora_is_valid_pid');
      $mapping = [];
      foreach ($valid_pids as $valid_pid) {
        $mapping[$valid_pid] = "\"{$valid_pid}\"";
      }
      if (!empty($mapping)) {
        $qp = new IslandoraSolrQueryProcessor();
        $qp->buildQuery(strtr('PID:(!pids)', [
          '!pids' => implode(' OR ', $mapping),
        ]));
        $label_field = \Drupal::config('islandora_solr.settings')->get('islandora_solr_object_label_field');
        $qp->solrParams['facet'] = 'false';
        $qp->solrParams['fl'] = "PID, $label_field";
        $qp->solrLimit = count($mapping);
        $qp->executeQuery(FALSE, TRUE);
        $labels = [];
        if ($qp->islandoraSolrResult['response']['numFound'] > 0) {
          foreach ($qp->islandoraSolrResult['response']['objects'] as $doc) {
            $labels[$doc['PID']] = $doc['object_label'];
          }
        }
      }
    }
    foreach ($results as $values) {
      $bucket = $values['bucket'];
      $filter = $values['filter'];
      $count = $values['count'];

      // Replace link bucket with object label based on facet field settings.
      if ($replace_bucket) {
        $label = NULL;
        $pid = $pid_mapper($values);
        if (isset($labels[$pid])) {
          $label = $labels[$pid];
        }
        // Fall back to islandora object if PID is not in solr.
        // eg: content models.
        else {
          if ($object = islandora_object_load($pid)) {
            $label = $object->label;
          }
        }
        $bucket = ($label ? $label : $bucket);
      }

      // Replace labels for boolean values if necessary.
      if ($bucket == 'true' && isset($this->settings['boolean_facet_true_replacement']) && !empty($this->settings['boolean_facet_true_replacement'])) {
        $bucket = $this->settings['boolean_facet_true_replacement'];
      }
      if ($bucket == 'false' && isset($this->settings['boolean_facet_false_replacement']) && !empty($this->settings['boolean_facet_false_replacement'])) {
        $bucket = $this->settings['boolean_facet_false_replacement'];
      }

      // Current URL query.
      $fq = isset($islandora_solr_query->solrParams['fq']) ? $islandora_solr_query->solrParams['fq'] : [];
      // 1: Check minimum count.
      // 2: Check if the filter isn't active.
      if ($count < self::$minimum_count || array_search($filter, $fq) !== FALSE) {
        continue;
      }
      // Current path including query, for example islandora/solr/query.
      // $_GET['q'] didn't seem to work here.
      $path = Url::fromRoute('<current>')->toString();
      // Parameters set in URL.
      $params = $islandora_solr_query->internalSolrParams;
      // Set filter key if there are no filters included.
      if (!isset($params['f'])) {
        $params['f'] = [];
      }
      // Merge recursively to add new filter parameter.
      $query_plus = array_merge_recursive($params, ['f' => [$filter]]);
      $query_minus = array_merge_recursive($params, ['f' => ['-' . $filter]]);

      // Set basic attributes.
      $attributes = [
        'link' => [
          'path' => $path,
        ],
        'plus' => [
          'path' => $path,
        ],
        'minus' => [
          'path' => $path,
        ],
      ];
      $attribute_array = ['rel' => 'nofollow'];
      $attributes['link']['attr'] = new Attribute($attribute_array);
      $attributes['minus']['attr'] = new Attribute($attribute_array);
      $attributes['plus']['attr'] = new Attribute($attribute_array);
      $attributes['plus']['attr']['href'] = Url::fromRoute('<current>', [], ['query' => $query_plus])->toString();
      $attributes['link']['attr']['href'] = Url::fromRoute('<current>', [], ['query' => $query_plus])->toString();
      $attributes['minus']['attr']['href'] = Url::fromRoute('<current>', [], ['query' => $query_minus])->toString();

      $attributes['link']['query'] = $attributes['plus']['query'] = $query_plus;

      $attributes['minus']['query'] = $query_minus;

      $attributes['plus']['attr']['class'] = ['plus'];
      $attributes['minus']['attr']['class'] = ['minus'];

      module_load_include('inc', 'islandora', 'includes/utilities');

      $hooks = islandora_build_hook_list(ISLANDORA_SOLR_FACET_BUCKET_CLASSES_HOOK_BASE);
      \Drupal::moduleHandler()->alter($hooks, $attributes, $islandora_solr_query);

      // XXX: We are not using l() because of active classes:
      // @see http://drupal.org/node/41595
      // Create link.
      $link['link']['#markup'] = '<a' . $attributes['link']['attr'] . '>' . $bucket . '</a>';
      $link['count']['#markup'] = $count;
      $link['link_plus']['#markup'] = '<a' . $attributes['plus']['attr'] . '>+</a>';
      $link['link_minus']['#markup'] = '<a' . $attributes['minus']['attr'] . '>-</a>';
      $link['value'] = $bucket;
      $buckets[] = $link;
    }

    // Show more link.
    if (count($buckets) > $soft_limit) {
      $buckets_visible = array_slice($buckets, 0, $soft_limit);
      $buckets_hidden = array_slice($buckets, $soft_limit);
      $this->content["hidden_$facet_field"] = [
        '#theme' => 'islandora_solr_facet',
        '#buckets' => $buckets_visible,
        '#hidden' => FALSE,
        '#pid' => $facet_field,
      ];
      $this->content["visible_$facet_field"] = [
        '#theme' => 'islandora_solr_facet',
        '#buckets' => $buckets_hidden,
        '#hidden' => TRUE,
        '#pid' => $facet_field,
        '#attached' => ['library' => ['islandora_solr/facets_js']],
      ];

      $this->content["more_$facet_field"]['#markup'] = '<a href="#" class="soft-limit">' . t('Show more') . '</a>';
    }
    elseif (!empty($buckets)) {
      $this->content[$facet_field] = [
        '#theme' => 'islandora_solr_facet',
        '#buckets' => $buckets,
        '#hidden' => FALSE,
        '#pid' => $facet_field,
      ];
    }
  }

  /**
   * Prepare facet dates for slider.
   *
   * Preparing this array includes:
   * - Stripping dates highest and lowest end that have no results when
   *   variable range gap is used.
   * - Calculate the range gap based on the first two results.
   * - Based on the calculation, name the range and assign date formatting.
   * - Add color as set in user configuration.
   */
  public function prepareFacetDatesSlider() {
    $facet_field = $this->facet_field;
    $settings = $this->settings;
    $results = $this->results;
    $needed_solr_call = self::$needed_solr_call;
    if (!isset($this->sliderKey)) {
      $this->sliderKey = self::$range_slider_key++;
    }
    $range_slider_key = $this->sliderKey;
    $results_end = $results['end'];
    foreach (self::$exclude_range_values as $exclude) {
      unset($results[$exclude]);
    }
    if ($this->facet_type == 'facet_ranges' && isset($results['counts'])) {
      $results = $results['counts'];
    }

    // Strip empty buckets top and bottom when no date range filters are set.
    if (!in_array($facet_field, $needed_solr_call)) {
      // Strip top.
      foreach ($results as $bucket => $count) {
        if ($count == 0) {
          unset($results[$bucket]);
        }
        else {
          break;
        }
      }
      // Reverse and strip other side.
      $results = array_reverse($results);
      $new_end = [];
      foreach ($results as $bucket => $count) {
        if ($count == 0) {
          unset($results[$bucket]);
          $new_end = ['bucket' => $bucket, 'count' => $count];
        }
        else {
          break;
        }
      }
      // Reverse to normal order.
      $results = array_reverse($results);

      // Add end date.
      if (isset($new_end['count']) and $new_end['count'] == 0) {
        $end_bucket = $new_end['bucket'];
        $results[$end_bucket] = NULL;
      }
      else {
        $results[$results_end] = NULL;
      }
    }
    // Do not strip empty buckets left and right when filters are set.
    else {
      // Add end date.
      $results[$results_end] = NULL;
    }

    // If values are available.
    if (count($results) <= 1) {
      return [];
    }

    // Calculate gap.
    $calc_from = strtotime(key($results));
    next($results);
    $calc_to = strtotime(key($results));
    // Calculate difference between from and to date.
    $calc_diff = abs($calc_from - $calc_to);

    // Total difference in days.
    $calc_total_days = floor($calc_diff / 60 / 60 / 24);

    // @todo Could be done nicer?
    // @todo $date_format configurable.
    // Get gap based on total days diff.
    $gap = NULL;
    $date_format = 'Y';
    if ($calc_total_days < 7) {
      $gap = t('days');
      $date_format = 'M j, Y';
    }
    elseif ($calc_total_days >= 7 && $calc_total_days <= 28) {
      $gap = t('weeks');
      $date_format = 'M j, Y';
    }
    elseif ($calc_total_days >= 28 && $calc_total_days <= 32) {
      $gap = t('months');
      $date_format = 'M Y';
    }
    elseif ($calc_total_days >= 360 && $calc_total_days <= 370) {
      $gap = t('years');
      $date_format = 'Y';
    }
    elseif ($calc_total_days >= 720 && $calc_total_days <= 740) {
      $gap = t('2 years');
      $date_format = 'Y';
    }
    elseif ($calc_total_days >= 1800 && $calc_total_days <= 1850) {
      $gap = t('5 years');
      $date_format = 'Y';
    }
    elseif ($calc_total_days >= 3600 && $calc_total_days <= 3700) {
      $gap = t('decades');
      $date_format = 'Y';
    }
    elseif ($calc_total_days >= 36000 && $calc_total_days <= 37000) {
      $gap = t('centuries');
      $date_format = 'Y';
    }
    elseif ($calc_total_days >= 360000 && $calc_total_days <= 370000) {
      $gap = t('millennia');
      $date_format = 'Y';
    }

    // Create a nice array with our data.
    $data = [];
    foreach ($results as $bucket => $count) {
      $bucket_formatted = format_date(strtotime(trim($bucket)) + 1, 'custom', $date_format, 'UTC');

      $bucket_formatted = str_replace(' ', '&nbsp;', $bucket_formatted);
      $data[] = [
        'date' => $bucket,
        'bucket' => $bucket_formatted,
        'count' => $count,
      ];
    }

    // Add range slider color.
    if (isset($settings['range_facet_slider_color']) &&
        !empty($settings['range_facet_slider_color'])) {
      $slider_color = $settings['range_facet_slider_color'];
    }
    else {
      $slider_color = '#edc240';
    }

    $elements = [
      'data' => $data,
      'facet_field' => $facet_field,
      'slider_color' => $slider_color,
      'gap' => $gap,
      'date_format' => $date_format,
      'form_key' => $range_slider_key,
    ];

    return $elements;
  }

  /**
   * Render slider.
   *
   * Based on the prepared results array, render the slider. The rendered
   * slider is a form. It passes the array and returns and renders the form.
   */
  public function renderSlider($facet_dates_as_slider) {
    $elements = $facet_dates_as_slider;

    // XXX: We do not want this to possibly grab from the cache when AJAXing,
    // since $elements changes... Makes a mess of rebuilding.
    $old_build_id = (
      // ... our form...
      isset($_POST['form_id']) && strpos($_POST['form_id'], 'islandora_solr_range_slider_form_') === 0 &&
      // ... AJAXing...
      isset($_POST['ajax_page_state']) &&
      // ... with the build ID.
      isset($_POST['form_build_id'])) ? $_POST['form_build_id'] : NULL;
    if (isset($old_build_id)) {
      $_POST['form_build_id'] = NULL;
    }

    $form_object = \Drupal::service('class_resolver')->getInstanceFromDefinition(IslandoraRangeSlider::class);
    $form_object->setType($elements['form_key']);
    $range_slider_form = \Drupal::formBuilder()->getForm($form_object, $elements);

    if (isset($old_build_id)) {
      // XXX: Restore the build ID to $_POST, just in case.
      $_POST['form_build_id'] = $old_build_id;
    }
    $this->content["slider_$old_build_id"] = $range_slider_form;
  }

  /**
   * Prepare facet dates filter.
   *
   * Prepares an array with settings to render a date/range filter.
   */
  public function prepareFacetDatesFilter() {
    $settings = $this->settings;
    // Datepicker range.
    if (isset($settings['date_filter_datepicker_range']) && !empty($settings['date_filter_datepicker_range'])) {
      $datepicker_range = $settings['date_filter_datepicker_range'];
    }
    else {
      $datepicker_range = '-100:+3';
    }
    $elements = [
      'facet_field' => $this->facet_field,
      'datepicker_range' => $datepicker_range,
      'form_key' => self::$date_filter_key,
    ];
    self::$date_filter_key++;

    return $elements;
  }

  /**
   * Renders a facet dates filter.
   *
   * Based on the prepared array with elements, this method passes the array to
   * a form, returns it and then renders the form.
   */
  public function renderFacetDatesFilter($elements) {
    $form_object = \Drupal::service('class_resolver')->getInstanceFromDefinition(IslandoraDateFilter::class);
    $form_object->setType($elements['form_key']);
    $date_filter_form = \Drupal::formBuilder()->getForm($form_object, $elements);
    if (!empty($this->content)) {
      $this->content["date_filter{$elements['form_key']}"] = $date_filter_form;
    }
  }

  /**
   * Variable date gap.
   *
   * This method calculates the upper and lower ranges of a date range facet.
   * It also performs a second Solr query execution if necessary to recalculate
   * the date range facet with a new range gap.  This is a static function
   * because it calculates multiple date range facets, so in case multiple
   * facets need recalculation, it can do this in one Solr call, which saves on
   * performance. Statscomponent will be used in the future for range facets in
   * Solr 3.5 or higher.
   *
   * Tasks this method performs:
   * - 1: Gather info.
   * - 2: Calculate and render range gap.
   * - 3: Execute.
   * - 4: Prepare for return.
   * - 5: Update date facets.
   */
  public static function variableDateGap() {
    // 1: Gather info.
    // @todo Move this to separate functions.
    // Check settings.
    $facet_fields_settings = self::$facet_fields_settings;
    $variable_date_gap = [];
    foreach ($facet_fields_settings as $settings) {
      if (isset($settings['range_facet_variable_gap']) && $settings['range_facet_variable_gap']) {
        $variable_date_gap[] = $settings['solr_field'];
      }
    }

    $islandora_solr_query = self::$islandoraSolrQuery;
    $fq = isset($islandora_solr_query->solrParams['fq']) ? $islandora_solr_query->solrParams['fq'] : [];
    $facet_dates = self::$facet_dates;
    // Populate with terms that needed a second solr call to update facets.
    $needs_solr_call = [];
    // Loop over all date facets.
    foreach ($facet_dates as $solr_field => $buckets) {
      $values = [];
      // Loop over all filters.
      foreach ($fq as $filter) {
        // Check for enabled range filters.
        if (strpos($filter, $solr_field) === FALSE) {
          continue;
        }
        // Don't include excluded ranges for now, because it's complicated.
        // Maybe keep it like this for Solr 1.4. Solr 3.5 and up would
        // calculate this through the StatsComponent.
        if (substr($filter, 0, 1) == '-') {
          continue;
        }
        // @todo This could be done differently?
        // Check 'variable range gap' settings for this field.
        if (!in_array($solr_field, $variable_date_gap)) {
          continue;
        }
        // Split the filter into field and value.
        $filter_split = preg_split(ISLANDORA_SOLR_QUERY_FIELD_VALUE_SPLIT_REGEX, $filter, 2);
        // Trim brackets.
        $filter_split[1] = trim($filter_split[1], "\"");
        // Split range filter string to return formatted date values.
        $filter_str = $filter_split[1];
        $filter_str = trim($filter_str, '[');
        $filter_str = trim($filter_str, ']');
        $filter_array = explode(' TO ', $filter_str);
        // Collect values in array: timestamp => ISO8601.
        $from_str = trim($filter_array[0]);
        $to_str = trim($filter_array[1]);
        $field = trim($filter_split[0], ' -');
        // If a star is given, we need to perform an extra query to find out
        // what the minimum or maximum value is that 'star' would return.
        if (strpos($from_str, '*') !== FALSE) {
          $from_str = self::findMinMaxValue($field, 'asc');
        }
        if (strpos($to_str, '*') !== FALSE) {
          $to_str = self::findMinMaxValue($field, 'desc');
        }
        $values['from'][strtotime(strtolower($from_str))] = $from_str;
        $values['to'][strtotime(strtolower($to_str))] = $to_str;
      }

      // If the date facet field is found as at least one range filter,
      // calculate gap.
      if (empty($values)) {
        continue;
      }

      // From max value & to min value.
      $from_unix = max(array_keys($values['from']));
      $to_unix = min(array_keys($values['to']));

      // If the from date is bigger than the to date, abort.
      if ($from_unix >= $to_unix) {
        continue;
      }

      // Get ISO8601 values.
      $from = $values['from'][$from_unix];
      // If the hour is 00:00:00, subtract one second. If we always subtract
      // one second we keep eating away seconds after every filter.
      if (strpos($from, '00:00:00') !== FALSE) {
        $from .= '-1SECOND';
      }
      $to = $values['to'][$to_unix];

      // 2: Calculate and render range gap.
      // Calculate difference between from and to date.
      $diff = abs($from_unix - $to_unix);

      // Total difference in days.
      $total_days = floor($diff / 60 / 60 / 24);

      // @todo Make max buckets variable.
      // @todo Fine tune this: it's not very precise.
      // For maximum 15 buckets.
      if ($total_days <= 15) {
        $gap = '+1DAY';
      }
      elseif ($total_days <= 105) {
        $gap = '+7DAYS';
      }
      elseif ($total_days <= 450) {
        $gap = '+1MONTH';
      }
      elseif ($total_days <= 5475) {
        $gap = '+1YEAR';
      }
      elseif ($total_days <= 10950) {
        $gap = '+2YEARS';
      }
      elseif ($total_days <= 18250) {
        $gap = '+5YEARS';
      }
      elseif ($total_days <= 54750) {
        $gap = '+10YEARS';
      }
      elseif ($total_days <= 547500) {
        $gap = '+100YEARS';
      }
      elseif ($total_days <= 5475000) {
        $gap = '+1000YEARS';
      }

      // @todo Try to find a way to clone this object, because it's passed by
      // reference throughout the entire page call.
      // Update range facet values.
      $islandora_solr_query->solrParams["f.$solr_field.facet.date.start"] = $from;
      $islandora_solr_query->solrParams["f.$solr_field.facet.date.end"] = $to;
      $islandora_solr_query->solrParams["f.$solr_field.facet.date.gap"] = $gap;

      // Update variable.
      $needs_solr_call[] = $solr_field;
    }

    // 3: Execute.
    // If an extra solr call is necessary.
    if (!empty($needs_solr_call)) {
      // New query processor class.
      $range_query = new IslandoraSolrQueryProcessor();

      // Internal Solr query.
      $range_query->internalSolrQuery = $islandora_solr_query->internalSolrQuery;
      $range_query->solrLimit = 0;
      $range_query->solrStart = 0;
      $range_query->solrQuery = $islandora_solr_query->solrQuery;
      $range_query->solrParams = $islandora_solr_query->solrParams;
      // No need to include normal facets.
      unset($range_query->solrParams['facet.field']);

      // Excecute query.
      $range_query->executeQuery();

      // 4. Run query.
      $response_array = $range_query->islandoraSolrResult;

      // 5: Update date facets.
      self::$facet_dates = $response_array['facet_counts']['facet_dates'];
    }
    self::$needed_solr_call = $needs_solr_call;
  }

  /**
   * Finds the maximum or minimum value of a date field in a query.
   *
   * Called when one of the values in a date range filter equals to '*'.
   *
   * @param string $field
   *   Solr field to sort on.
   * @param string $order
   *   Sort order (asc or desc). Defaults to ascending.
   *
   * @return string
   *   Maximum or minimum value of a date field in the current query.
   */
  public static function findMinMaxValue($field, $order = 'asc') {
    $islandora_solr_query = self::$islandoraSolrQuery;

    // New query processor class.
    $min_max_query = new IslandoraSolrQueryProcessor();
    $min_max_query->internalSolrQuery = $islandora_solr_query->internalSolrQuery;
    $min_max_query->solrLimit = 1;
    $min_max_query->solrStart = 0;
    $min_max_query->solrQuery = $islandora_solr_query->solrQuery;
    $min_max_query->solrParams = $islandora_solr_query->solrParams;
    // No need to include normal facets.
    unset($min_max_query->solrParams['facet.field']);
    // Add the right sorting.
    $min_max_query->solrParams['sort'] = $field . ' ' . $order;
    $min_max_query->executeQuery();
    // Solr results.
    $results = $min_max_query->islandoraSolrResult;
    return $results['response']['objects'][0]['solr_doc'][$field];
  }

}
