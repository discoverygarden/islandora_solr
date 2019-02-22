<?php

namespace Drupal\islandora_solr\Form;

/**
 * Form to configure a Solr search field.
 */
class ConfigureSearchField extends ConfigFieldFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_configure_search_field_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldType() {
    return 'search_fields';
  }

}
