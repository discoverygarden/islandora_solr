<?php

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\islandora\Form\ModuleHandlerAdminForm;

/**
 * Compound backend form.
 */
class CompoundBackendAdmin extends ModuleHandlerAdminForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_compound_backend_admin';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['islandora_solr.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('islandora_solr.settings');

    $form['islandora_solr_compound_relationship_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Solr compound relationship field'),
      '#description' => $this->t('Solr field containing the compound relationship. Defaults to RELS_EXT_isConstituentOf_uri_ms'),
      '#default_value' => $config->get('islandora_solr_compound_relationship_field'),
      '#autocomplete_route_name' => 'islandora_solr.autocomplete_luke',
    ];
    $form['islandora_solr_compound_sequence_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Solr compound sequence pattern'),
      '#description' => $this->t('Compound sequences are stored with a unique relationship, if you index these in Solr provide the field name with %PID% in place of the actual escaped pid to use the SOLR Compound Member Query. Defaults to RELS_EXT_isSequenceNumberOf%PID%_literal_ms'),
      '#default_value' => $config->get('islandora_solr_compound_sequence_pattern'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('islandora_solr.settings');
    $default_pattern = $config->get('islandora_solr_compound_sequence_pattern');

    $pattern = $form_state->getValue('islandora_solr_compound_sequence_pattern');
    if ($pattern && $pattern != $default_pattern) {
      if (strpos($pattern, '%PID%') === FALSE) {
        $form_state->setErrorByName('islandora_solr_compound_sequence_pattern', $this->t(
          'Your pattern MUST contain %PID% where the converted PID will appear.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('islandora_solr.settings');

    $config->set('islandora_solr_compound_relationship_field', $form_state->getValue('islandora_solr_compound_relationship_field'));
    $config->set('islandora_solr_compound_sequence_pattern', $form_state->getValue('islandora_solr_compound_sequence_pattern'));

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
