<?php

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * The range slider form.
 */
class IslandoraRangeSlider extends BaseSubForm {
  const BASE_ID = 'islandora_solr_range_slider_form';

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $elements = []) {
    global $_islandora_solr_queryclass;

    $gap = $elements['gap'];
    $facet_field = $elements['facet_field'];
    $form_key = $elements['form_key'];
    $slider_color = $elements['slider_color'];
    $date_format = $elements['date_format'];
    $data = $elements['data'];

    $from_default = current($data);
    $to_default = end($data);
    if (!empty($gap)) {
      $gap = "({$gap})";
    }

    $form['#tree'] = TRUE;
    $form['#prefix'] = '<div class="islandora-solr-range-slider">';
    $form['#suffix'] = '</div>';
    // Field.
    $form['range_slider_term'] = [
      '#type' => 'hidden',
      '#value' => $facet_field,
      '#name' => 'range_slider_term_' . $form_key,
    ];
    $slider_element = [
      '#theme' => 'islandora_solr_range_slider',
      '#form_key' => $form_key,
      '#gap' => $gap,
      '#range_from' => $this->dateFormatter->format(strtotime(trim($from_default['date'])) + 1, 'custom', $date_format, 'UTC'),
      '#range_to' => $this->dateFormatter->format(strtotime(trim($to_default['date'])), 'custom', $date_format, 'UTC'),
    ];
    $form['markup'] = $slider_element;

    // Hidden from.
    $form['range_slider_hidden_from'] = [
      '#type' => 'hidden',
      '#default_value' => $from_default['date'],
      '#attributes' => ['class' => ['range-slider-hidden-from-' . $form_key]],
    ];
    // Hidden to.
    $form['range_slider_hidden_to'] = [
      '#type' => 'hidden',
      '#default_value' => $to_default['date'],
      '#attributes' => ['class' => ['range-slider-hidden-to-' . $form_key]],
    ];
    $form['range_slider_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#name' => 'range_slider_submit_' . $form_key,
      '#form_key' => $form_key,
    ];

    // Include flot.
    // @TODO: use the new version of flot. Didn't work out of the box, so needs
    // some extra attention.
    $form['#attached']['library'][] = 'islandora_solr/slider';
    $form['#attached']['drupalSettings']['islandora_solr']['islandoraSolrRangeSlider'][$facet_field] = [
      'facet_field' => $facet_field,
      'form_key' => $form_key,
      'data' => $data,
      'slider_color' => $slider_color,
    ];

    $this->renderer->addCacheableDependency($form, $_islandora_solr_queryclass);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Set variables.
    global $_islandora_solr_queryclass;
    $params = isset($_islandora_solr_queryclass->internalSolrParams) ? $_islandora_solr_queryclass->internalSolrParams : [];

    $term = $form_state->getValue('range_slider_term');

    // Date.
    $from = $form_state->getValue('range_slider_hidden_from');
    $to = $form_state->getValue('range_slider_hidden_to');

    $filter = "{$term}:[{$from} TO {$to}]";

    // Set date filter key if there are no date filters included.
    if (isset($params['f'])) {
      foreach ($params['f'] as $key => $f) {
        if (strpos($f, $term) !== FALSE) {
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
