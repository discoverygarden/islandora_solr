<?php

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form to set collection sort string.
 */
class IslandoraSolrManageCollectionSortForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_manage_collection_sort_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $object = NULL) {
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/db');
    $current_default = islandora_solr_get_collection_sort_string($object->id);
    $form_state->set('collection', $object->id);
    $collection_sort = $this->config('islandora_solr.settings')->get('islandora_solr_collection_sort');
    $base_sort = $this->config('islandora_solr.settings')->get('islandora_solr_base_sort');
    return [
      '#action' => Url::fromRoute(
        '<current>',
        [],
        ['fragment' => '#manage-collection-solr-sort']
      )->toString(),
      'collection_sort_string' => [
        '#type' => 'textfield',
        '#title' => $this->t('Solr Collection Sort String'),
        '#description' => $this->t('One or more non-multivalued Solr fields to sort by when using the Solr collection query backend (by convention, multivalued fields have names that contain "_m" plus another letter at the end of their Solr names). Add " asc" or " desc" to each fieldname. If this setting is empty, this collection will to fall back to the global sort settings in the order listed below.</br>Global Collection Sort: %collection_sort</br>Global Base Sort: %base_sort</br>', [
          '%collection_sort' => empty($collection_sort) ? $this->t("Not set") : $collection_sort,
          '%base_sort' => empty($base_sort) ? $this->t("Not set") : $base_sort,
        ]),
        '#default_value' => $current_default,
        // These strings can get big haha ...
        '#size' => 100,
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Apply'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    islandora_solr_set_collection_sort_string($form_state->get('collection'), $form_state->getValue('collection_sort_string'));
  }

}
