<?php

namespace Drupal\islandora_solr\Form;

/**
 * Form to configure a Solr sort field.
 */
class ConfigureSortField extends ConfigFieldFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_configure_sort_field_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldType() {
    return 'sort_fields';
  }

}
