<?php

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Base form for configuring types of fields.
 */
abstract class ConfigFieldFormBase extends ConfigFormBase {

  const FIELD_TYPE = 'base_field';

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['islandora_solr.fields'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_configure_' . static::FIELD_TYPE . '_form';
  }

  /**
   * Provides the field type being configured.
   *
   * @return string
   *   One of the field types, either 'result_fields', 'facet_fields',
   *   'sort_fields', or 'search_fields'.
   */
  protected static function getFieldType() {
    return static::FIELD_TYPE . 's';
  }

  /**
   * Gets the appropriate field format for its configuration.
   *
   * @param array $solr_field_settings
   *   The values to put into the configuration. Should likely come from the
   *   form state's values.
   *
   * @return array
   *   An array containing the configuration to apply to this field.
   */
  public static function getFieldConfiguration(array $solr_field_settings) {
    $data_type = 'islandora_solr.' . static::getFieldType();
    $config_service = \Drupal::service('config.typed');
    $fields = $config_service->getDefinition($data_type);
    $relevant_values = array_intersect_key($solr_field_settings, $fields['mapping']);
    $defaults = static::fieldConfigurationDefaults();
    $relevant_values += $defaults;

    assert($config_service->createFromNameAndData($data_type, $relevant_values)->validate()->count() === 0, 'Configuration matches schema.');

    return $relevant_values;
  }

  /**
   * Helper; define default values for fields.
   *
   * @return array
   *   An array matching the schema for this type of field, mapping
   *   the property names to default values.
   */
  protected static function fieldConfigurationDefaults() {
    return [
      'label' => '',
      'enable_permissions' => FALSE,
      'permissions' => [],
      'weight' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $solr_field = NULL) {
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/admin');
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/db');
    $form['#prefix'] = '<div id="field_modal">';
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
    $this->appendPermissionsAndActions($values, $form, $form_state, TRUE, [$this, 'modalSubmit']);
    return $form;
  }

  /**
   * Utility function to append permissions and actions to the modal.
   *
   * @param array $values
   *   An array of values.
   * @param array $form
   *   An array representing the Drupal form, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param bool $default_value
   *   Whether the default enabled checkbox is to be TRUE or FALSE.
   * @param callable $callback
   *   The callback for the modal ajax.
   */
  protected function appendPermissionsAndActions(array $values, array &$form, FormStateInterface $form_state, $default_value = TRUE, callable $callback = NULL) {
    $form_state->loadInclude('inc', 'islandora_solr', 'includes/admin');
    // Use perms only if enabled.
    $permissions = $values['enable_permissions'] ?
      $values['permissions'] :
      $values['enable_permissions'];
    $permissions_disable = _islandora_solr_permissions_disable();
    $permissions_default = _islandora_solr_permissions_default();

    $form['options']['permissions_fieldset'] = islandora_solr_get_admin_permissions_fieldset($permissions, $permissions_default, $permissions_disable, $default_value);

    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-buttons']],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#weight' => 5,
      '#field' => 'dialog_submit',
      '#field_type' => 'result_fields',
      '#name' => 'result-fields-dialog-submit',
      '#attributes' => [
        'class' => [
          'use-ajax',
        ],
      ],
      '#ajax' => [
        'callback' => $callback,
        'event' => 'click',
      ],
    ];
  }

  /**
   * Non-reloading ajax submit handler.
   */
  public function modalSubmit(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#facet_fields_modal', $form));
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
    $field_type = static::getFieldType();
    $field_name = $this->getRequest()->get('solr_field');
    $field_key = static::generateFieldKey($field_name);
    $values = $form_state->getValues();
    $config = static::getFieldConfiguration($values);
    $config['solr_field'] = $field_name;
    $this->config('islandora_solr.fields')
      ->set("$field_type.$field_key", $config)
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Turns a Solr field name into a valid config key.
   *
   * Basically just squashes dots into underscores. Literally. A big dude comes
   * in and steps on them.
   *
   * @param string $field_name
   *   The Solr field name.
   *
   * @return string
   *   The same field name, valid for use as a map key.
   */
  public static function generateFieldKey($field_name) {
    return str_replace('.', '_', $field_name);
  }

}
