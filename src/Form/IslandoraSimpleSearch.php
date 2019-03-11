<?php

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * The simple search form.
 */
class IslandoraSimpleSearch extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_simple_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['simple'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'container-inline',
        ],
      ],
    ];
    $form['simple']["islandora_simple_search_query"] = [
      '#size' => '15',
      '#type' => 'textfield',
      '#title' => $this->t("Search Term"),
      '#default_value' => '',
    ];
    $form['simple']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('search'),
    ];
    $form['#cache'] = [
      'contexts' => [
        'user.permissions',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/utilities');
    $search_string = islandora_solr_replace_slashes($form_state->getValue('islandora_simple_search_query'));

    $query = ['type' => 'dismax'];

    $form_state->setRedirect(
      'islandora_solr.islandora_solr',
      ['query' => $search_string],
      ['query' => $query]
    );
  }

}
