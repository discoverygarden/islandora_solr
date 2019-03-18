<?php

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form to configure a Solr facet field.
 */
class ConfigureFacetField extends ConfigFieldFormBase {
  const FIELD_TYPE = 'facet_field';

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $solr_field = NULL) {
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/admin');
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/db');
    $form['#prefix'] = '<div id="facet_fields_modal">';
    $form['#suffix'] = '</div>';

    $form_state->setStorage(['solr_field' => $solr_field]);
    $values = islandora_solr_get_field_configuration(static::getFieldType(), $solr_field);

    $form['options'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['scroll']],
      '#id' => 'islandora-solr-admin-dialog-form',
    ];
    $form['options']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => isset($values['label']) ? $values['label'] : '',
      '#description' => $this->t('A human-readable name.'),
    ];
    $form['options']['sort_by'] = [
      '#type' => 'radios',
      '#title' => $this->t('Sort by'),
      '#default_value' => isset($values['sort_by']) ? $values['sort_by'] : 'count',
      '#options' => [
        'count' => $this->t('Count of facet (numerically)'),
        'index' => $this->t('Text labels (alphabetically)'),
      ],
      '#description' => $this->t('Facets can be sorted by text label or the count of the facet. If you sort by text labels AND replace PID with object label your sort order is not guaranteed.'),
    ];

    if (islandora_solr_is_date_field($solr_field)) {
      // Add in defaults, to avoid isset() tests.
      $values += [
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
      ];
      $form['options']['date_facet_format'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Date format'),
        '#default_value' => $values['date_facet_format'],
        '#description' => $this->t('The format of the date, as it will be displayed in the facet block. Use <a href="@url">PHP date()</a> formatting. Works best when the date format matches the granularity of the source data. Otherwise it is possible that there will be duplicates displayed.', ['@url' => 'http://php.net/manual/function.date.php']),
      ];

      $form['options']['range_facet'] = [
        '#type' => 'fieldset',
        '#id' => 'range-facet-wrapper',
        '#collapsible' => FALSE,
        '#collapsed' => TRUE,
      ];

      // @todo Grey out if LUKE says it's not possible to use as a range field?
      //   Add AJAX callback to show more options?
      $form['options']['range_facet']['range_facet_select'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Range facet'),
        '#default_value' => $values['range_facet_select'],
        '#description' => $this->t('Toggles whether this facet field should be configured as a Solr range facet.'),
      ];

      // @todo Check for non-ajax values.
      $form['options']['range_facet']['wrapper'] = [
        '#type' => 'container',
        '#states' => [
          'visible' => [
            ':input[name="range_facet_select"]' => ['checked' => TRUE],
          ],
        ],
      ];
      $form['options']['range_facet']['wrapper']['range_facet_variable_gap'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Variable range gap'),
        '#return_value' => 1,
        '#default_value' => $values['range_facet_variable_gap'],
        '#description' => $this->t('When checked, the following date range settings will be used by default, but if a date range is filtered down, a new range gap will be calculated and applied. When left unchecked, the following settings provide fixed range gaps.'),
      ];
      $form['options']['range_facet']['wrapper']['range_facet_start'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Start'),
        '#default_value' => $values['range_facet_start'],
        '#description' => $this->t('The lower bound of the first date range for all date faceting on this field. This should be a single date expression which may use the <a href="@url">DateMathParser</a> syntax.', ['@url' => 'http://lucene.apache.org/solr/api/org/apache/solr/util/DateMathParser.html']),
      ];
      $form['options']['range_facet']['wrapper']['range_facet_end'] = [
        '#type' => 'textfield',
        '#title' => $this->t('End'),
        '#default_value' => $values['range_facet_end'],
        '#description' => $this->t('The minimum upper bound of the last date range for all Date Faceting on this field. This should be a single date expression which may use the <a href="@url">DateMathParser</a> syntax.', ['@url' => 'http://lucene.apache.org/solr/api/org/apache/solr/util/DateMathParser.html']),
      ];
      $form['options']['range_facet']['wrapper']['range_facet_gap'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Gap'),
        '#default_value' => $values['range_facet_gap'],
        '#description' => $this->t('The size of each date range, expressed as an interval to be added to the lower bound using the <a href="@url">DateMathParser</a> syntax.', ['@url' => 'http://lucene.apache.org/solr/api/org/apache/solr/util/DateMathParser.html']),
      ];
      // Range slider.
      $form['options']['range_facet']['wrapper']['range_slider'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Range slider'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];
      $form['options']['range_facet']['wrapper']['range_slider']['range_facet_slider_enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable range slider'),
        '#return_value' => 1,
        '#default_value' => $values['range_facet_slider_enabled'],
        '#description' => $this->t('When checked, the normal range facet will be replaced by a range slider widget.'),
      ];
      $form['options']['range_facet']['wrapper']['range_slider']['range_facet_slider_color'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Slider color'),
        '#description' => $this->t('The range slider\'s color, formatted as a hex value. Defaults to <span style="color: #edc240">#edc240</span>'),
        '#default_value' => $values['range_facet_slider_color'],
        '#states' => [
          'visible' => [
            ':input[name="range_facet_slider_enabled"]' => ['checked' => TRUE],
          ],
        ],
      ];

      // Date filter.
      $form['options']['range_facet']['wrapper']['date_filter'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Date range filter'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];
      $form['options']['range_facet']['wrapper']['date_filter']['date_filter_datepicker_enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable date range filter'),
        '#return_value' => 1,
        '#default_value' => $values['date_filter_datepicker_enabled'],
        '#description' => $this->t('When checked, a date range filter will become available underneath the date range facet. The date range filter includes <em>from date</em> and a <em>to date</em> text fields. It also comes with a calendar popup widget.'),
      ];
      $form['options']['range_facet']['wrapper']['date_filter']['date_filter_datepicker_range'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Years back and forward'),
        '#default_value' => $values['date_filter_datepicker_range'],
        '#size' => 10,
        '#maxsize' => 10,
        '#description' => $this->t('The range of years displayed in the year drop-down menu. These are either relative to today\'s year ("-nn:+nn"), to the currently selected year ("c-nn:c+nn"), an absolute ("nnnn:nnnn"), or combinations of these formats ("nnnn:-nn"). For more info, check the jQuery UI <a href="@url" target="_blank">datepicker documentation</a>.', ['@url' => 'http://api.jqueryui.com/datepicker/#option-yearRange']),
        '#states' => [
          'visible' => [
            ':input[name="date_filter_datepicker_enabled"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }
    elseif (islandora_solr_is_boolean_field($solr_field)) {
      // Defaults.
      $values += [
        'boolean_facet_true_replacement' => '',
        'boolean_facet_false_replacement' => '',
      ];
      $form['options']['boolean_facet'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Boolean Facet Options'),
        '#collapsible' => FALSE,
        'blurb' => [
          '#markup' => $this->t('Enter replacement values for the labels of TRUE and FALSE results for boolean facets. Leaving a field empty will cause that result to show up as "true" or "false", respectively, which may be less than clear to the end user.'),
        ],
        'boolean_facet_true_replacement' => [
          '#type' => 'textfield',
          '#title' => $this->t('Replacement for TRUE values'),
          '#default_value' => $values['boolean_facet_true_replacement'],
        ],
        'boolean_facet_false_replacement' => [
          '#type' => 'textfield',
          '#title' => $this->t('Replacement for FALSE values'),
          '#default_value' => $values['boolean_facet_false_replacement'],
        ],
      ];
    }

    // Permissions.
    $this->appendPermissionsAndActions($values, $form, $form_state, TRUE, [$this, 'modalSubmit']);

    $form['options']['pid_object_label'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Replace PID with Object Label'),
      '#return_value' => 1,
      '#default_value' => (isset($values['pid_object_label']) ? $values['pid_object_label'] : 0),
      '#description' => $this->t("Replace a PID (islandora:foo) or a URI (info:fedora/islandora:foo) with that object's label. Will only work with non-tokenized Solr fields (full literal strings)."),
    ];
    return $form;
  }

}
