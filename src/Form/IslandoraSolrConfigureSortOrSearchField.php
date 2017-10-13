<?php

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Form to configure a Solr sort or search field.
 */
class IslandoraSolrConfigureSortOrSearchField extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_configure_sort_or_search_field_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $solr_field = NULL, $field_type = NULL) {
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/admin');
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/db');
    $form['#prefix'] = '<div id="field_modal">';
    $form['#suffix'] = '</div>';

    $form_state->setStorage(['solr_field' => $solr_field, 'field_type' => $field_type]);
    $values = islandora_solr_get_field_configuration($field_type, $solr_field);

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
    islandora_solr_append_permissions_and_actions($values, $form, TRUE, [$this, 'modalSubmit']);
    return $form;
  }

  /**
   * Non-reloading ajax submit handler.
   */
  public function modalSubmit(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#field_modal', $form));
    }
    else {
      $response->addCommand(new OpenModalDialogCommand($this->t('Saved'), $this->t('The configuration has been saved.'), ['width' => 800]));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/admin');
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/db');
    $storage = $form_state->getStorage();
    $settings = _islandora_solr_handle_solr_field_settings($form_state->getValues(), $storage['field_type']);
    islandora_solr_set_field_configuration($storage['field_type'], $storage['solr_field'], $settings);
  }

}
