<?php

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * The date filter form.
 */
class IslandoraDateFilter extends FormBase {
  protected $type;

  /**
   * {@inheritdoc}
   */
  public function __construct($type) {
    $this->type = $type;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return "islandora_solr_date_filter_form_{$this->type}";
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $from = explode('/', $form_state->getValue(['date_filter', 'date_filter_from']));
    $to = explode('/', $form_state->getValue(['date_filter', 'date_filter_to']));
    $form_key = $form_state->getTriggeringElement()['#form_key'];
    // Default for month and day.
    $default = '01';

    // If the 'from' value is '*' just skip all checks.
    if (trim($from[0]) != '*') {
      // Apply some defaults.
      if (!isset($from[1])) {
        $from[1] = $default;
      }
      if (!isset($from[2])) {
        $from[2] = $default;
      }

      // Check from date.
      if (!checkdate(intval($from[1]), intval($from[2]), intval($from[0]))) {
        $form_state->setErrorByName($form_key . '][date_filter_from', $this->t('<em>From</em> date is not formatted correctly.'));
      }
    }
    // If the 'to' value is '*' just skip all checks.
    if (trim($to[0]) != '*') {
      // Apply some defaults.
      if (!isset($to[1])) {
        $to[1] = $default;
      }
      if (!isset($to[2])) {
        $to[2] = $default;
      }
      // Check to date.
      if (!checkdate(intval($to[1]), intval($to[2]), intval($to[0]))) {
        $form_state->setErrorByName($form_key . '][date_filter_to', $this->t('<em>To</em> date is not formatted correctly.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $elements = []) {
    global $_islandora_solr_queryclass;
    $facet_field = $elements['facet_field'];
    $form_key = $elements['form_key'];
    $datepicker_range = $elements['datepicker_range'];

    $form = [
      '#tree' => TRUE,
      '#prefix' => '<div class="islandora-solr-date-filter">',
      '#suffix' => '</div>',
    ];
    // Field.
    $form['date_filter_facet_field'] = [
      '#type' => 'hidden',
      '#value' => $facet_field,
      '#name' => 'date_filter_facet_field_' . $form_key,
    ];

    // Check if default value is possible.
    // Parameters set in URL.
    $params = isset($_islandora_solr_queryclass->internalSolrParams) ? $_islandora_solr_queryclass->internalSolrParams : [];
    $filter_count = 0;
    if (isset($params['f'])) {
      $format = 'Y/m/d';
      foreach ($params['f'] as $key => $filter) {
        if (strpos($filter, $facet_field) === 0) {
          $filter_count++;
          // Split the filter into field and value.
          $filter_split = preg_split(ISLANDORA_SOLR_QUERY_FIELD_VALUE_SPLIT_REGEX, $filter, 2);
          // Trim brackets.
          $filter_split[1] = trim($filter_split[1], "\"");
          // Split range filter string to return formatted date values.
          $filter_str = $filter_split[1];

          $filter_str = trim($filter_str, '[');
          $filter_str = trim($filter_str, ']');
          $filter_array = explode(' TO ', $filter_str);

          // Get timestamps.
          $from_unix = strtotime(trim($filter_array[0]));
          $to_unix = strtotime(trim($filter_array[1]));

          // Only set default times if from date is lower than to date.
          if ($from_unix < $to_unix) {
            if ($from_unix !== FALSE) {
              // XXX: Need to implement DI here, requires refactoring of instantiation calls using new.
              // @codingStandardsIgnoreStart
              $from_default = (strpos($filter_array[0], '*') !== FALSE) ? '*' : \Drupal::getContainer()->get('date.formatter')->format($from_unix, 'custom', $format, 'UTC');
              // @codingStandardsIgnoreEnd
            }
            else {
              $from_default = NULL;
            }
            if ($to_unix !== FALSE) {
              // XXX: Need to implement DI here, requires refactoring of instantiation calls using new.
              // @codingStandardsIgnoreStart
              $to_default = (strpos($filter_array[1], '*') !== FALSE) ? '*' : \Drupal::getContainer()->get('date.formatter')->format($to_unix, 'custom', $format, 'UTC');
              // @codingStandardsIgnoreEnd
            }
            else {
              $to_default = NULL;
            }
          }
          else {
            $from_default = NULL;
            $to_default = NULL;
          }
        }
      }
    }
    if ($filter_count != 1) {
      $from_default = NULL;
      $to_default = NULL;
    }

    if ($from_default != NULL || $to_default != NULL) {
      $class = 'date-range-expanded';
      $value = $this->t('Hide');
    }
    else {
      $class = 'date-range-collapsed';
      $value = $this->t('Show');
    }

    $form['date_range_expand'] = [
      '#markup' => $this->t('Specify date range: <a href="#" class="toggle-date-range-filter @class">@value</a>', ['@class' => $class, '@value' => $value]),
      '#prefix' => '<span class="date-filter-toggle-text">',
      '#suffix' => '</span>',
    ];
    $form['date_filter'] = [
      '#prefix' => '<div class="date-range-filter-wrapper">',
      '#suffix' => '</div>',
    ];
    $form['date_filter']['date_filter'] = [
      '#markup' => '<div class="description">' . $this->t('Format: @date', ['@date' => date("Y/m/d")]) . '</div>',
    ];
    $form['date_filter']['date_filter_from'] = [
      '#type' => 'textfield',
      '#title' => $this->t('From'),
      '#default_value' => ($from_default) ? $from_default : '',
      '#size' => 10,
      '#maxlength' => 10,
      '#attributes' => ['class' => ['islandora-solr-datepicker-' . $form_key]],
    ];
    $form['date_filter']['date_filter_to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('To'),
      '#default_value' => ($to_default) ? $to_default : '',
      '#size' => 10,
      '#maxlength' => 10,
      '#attributes' => ['class' => ['islandora-solr-datepicker-' . $form_key]],
    ];
    $form['date_filter']['date_filter_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#name' => 'date_filter_facet_field_' . $form_key,
      '#form_key' => $form_key,
    ];
    $form['#attached']['library'][] = 'core/jquery.ui.datepicker';
    $form['#attached']['drupalSettings']['islandora_solr']['islandoraSolrDatepickerRange'][$facet_field] = [
      'datepickerRange' => trim($datepicker_range),
      'formKey' => $form_key,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    global $_islandora_solr_queryclass;

    $params = isset($_islandora_solr_queryclass->internalSolrParams) ? $_islandora_solr_queryclass->internalSolrParams : [];
    $facet_field = $form_state->getValue('date_filter_facet_field');
    $form_key = $form_state->getTriggeringElement()['#form_key'];

    // Date.
    $from = explode('/', $form_state->getValue(['date_filter', 'date_filter_from']));
    $to = explode('/', $form_state->getValue(['date_filter', 'date_filter_to']));

    $build_date = function (array $date_values) {
      if (trim($date_values[0]) != '*') {
        // Apply some defaults.
        $default = '01';
        if (!isset($date_values[1])) {
          $date_values[1] = $default;
        }
        if (!isset($date_values[2])) {
          $date_values[2] = $default;
        }
        // Create date string.
        return format_string('@year-@month-@dayT00:00:00Z', array_combine(
          ['@year', '@month', '@day'],
          $date_values
        ));
      }
      else {
        return $date_values[0];
      }
    };

    $from_str = $build_date($from);
    $to_str = $build_date($to);

    // Create filter.
    $filter = "{$facet_field}:[{$from_str} TO {$to_str}]";

    // Set date filter key if there are no date filters included.
    if (isset($params['f'])) {
      foreach ($params['f'] as $key => $f) {
        if (strpos($f, $facet_field) !== FALSE) {
          array_splice($params['f'], $key);
        }
      }
      $params['f'][] = $filter;
      $query = $params;
    }
    else {
      $query = array_merge_recursive($params, ['f' => [$filter]]);
    }
    $form_state->setRedirect('<current>', [], ['query' => $query]);
  }

}
