<?php

/**
 * @file
 * This file contains DB query functions.
 */

use Drupal\islandora_solr\Form\ConfigFieldFormBase;
use Drupal\islandora_solr\Form\ConfigureFacetField;
use Drupal\islandora_solr\Form\ConfigureSortField;
use Drupal\islandora_solr\Form\ConfigureResultField;
use Drupal\islandora_solr\Form\ConfigureSearchField;

/**
 * Get the configuration of a Solr field.
 */
function islandora_solr_get_field_configuration($field_type, $field_name) {
  $machine_name = ConfigFieldFormBase::generateFieldKey($field_name);
  $field = \Drupal::config('islandora_solr.fields')->get("$field_type.$machine_name");
  return $field ?: FALSE;
}

/**
 * Gets a full default configuration for a given field type and defaults.
 *
 * @param string $solr_field
 *   The field to be given a default configuration.
 * @param string $field_type
 *   The type of field to get a default configuration for.
 * @param array $defaults
 *   An array of default values to override with.
 *
 * @return array
 *   A full configuration for the given field type.
 */
function islandora_solr_get_default_field_configuration($solr_field, $field_type, array $defaults = []) {
  $field_config_class = [
    'facet_fields' => ConfigureFacetField::class,
    'sort_fields' => ConfigureSortField::class,
    'result_fields' => ConfigureResultField::class,
    'search_fields' => ConfigureSearchField::class,
  ];
  $config = call_user_func_array([$field_config_class[$field_type], 'getFieldConfiguration'], [$defaults]);
  $config['solr_field'] = $solr_field;
  return $config;
}

/**
 * Performs a select query to get fields for any field type.
 *
 * @params string $field_type
 *   The type of field to query for. If not passed, the query returns all
 *   fields.
 * @params bool $filter
 *   If set to TRUE, it will filter the array against user roles. If set to
 *   FALSE, it will return all results.
 * @params bool $simplify
 *   Whether to return the fields array as a simple term => label array.
 *
 * @return array
 *   The query results as an array.
 *
 * @see islandora_solr_get_fields()
 */
function islandora_solr_get_fields($field_type = NULL, $filter = TRUE, $simplified = TRUE, $keys = FALSE) {
  $records = [];
  if (is_null($field_type)) {
    $types = [
      'search_fields',
      'sort_fields',
      'facet_fields',
      'result_fields',
    ];
  }
  else {
    $types = [$field_type];
  }
  foreach ($types as $type) {
    foreach (\Drupal::config('islandora_solr.fields')->get($type) as $field_definition) {
      $field_definition['field_type'] = $type;
      if ($keys && !$simplified) {
        $records[$field_definition['solr_field']] = $field_definition;
      }
      else {
        $records[] = $field_definition;
      }
    }
  }
  // Filter.
  if ($filter == TRUE) {
    $records = _islandora_solr_filter_fields($records);
  }
  // Simplify.
  if ($simplified == TRUE) {
    $records = _islandora_solr_simplify_fields($records);
  }
  return $records;
}

/**
 * Checks an array of fields DB records against user roles.
 *
 * @param array $records
 *   An array of fields.
 *
 * @return array
 *   The array of fields, filtered by user roles.
 *
 * @see islandora_solr_get_fields()
 */
function _islandora_solr_filter_fields(array $records = []) {
  $user = \Drupal::currentUser();
  // No checks for user 1.
  if ($user->id() == 1) {
    return $records;
  }
  $filtered_records = [];
  foreach ($records as $record) {
    // If field settings are set, check permissions.
    if (isset($record['permissions'])) {
      // Check field permissions against user roles.
      if (!empty(array_intersect($record['permissions'], $user->getRoles()))) {
        $filtered_records[] = $record;
      }
    }
    else {
      // If permissions are never explicitly set, include by default.
      $filtered_records[] = $record;
    }
  }
  return $filtered_records;
}

/**
 * Formats fields data from the DB as a clean array.
 *
 * @param array $fields
 *   Raw array from the DB with fields data.
 *
 * @return array
 *   Returns a cleaned up array as term => label
 *
 * @see islandora_solr_get_fields()
 */
function _islandora_solr_simplify_fields(array $fields = []) {
  // Render as term => label.
  $records = [];
  foreach ($fields as $value) {
    $term = $value['solr_field'];
    if (isset($value['label']) && !empty($value['label'])) {
      $label = $value['label'];
    }
    else {
      $label = $value['solr_field'];
    }
    $records[$term] = $label;
  }
  return $records;
}

/**
 * Return display fields with snippet highlighting enabled.
 */
function islandora_solr_get_snippet_fields() {
  $records = islandora_solr_get_fields('result_fields', TRUE, FALSE);
  $snippet_fields = [];
  foreach ($records as $value) {
    if (isset($value['snippet']) && $value['snippet']) {
      $snippet_fields[] = $value['solr_field'];
    }
  }
  return $snippet_fields;
}

/**
 * Return display fields with 'link to object' enabled.
 */
function islandora_solr_get_link_to_object_fields() {
  $records = islandora_solr_get_fields('result_fields', TRUE, FALSE);
  $link_to_object_fields = [];
  foreach ($records as $value) {
    if ($value['link_to_object']) {
      $link_to_object_fields[] = $value['solr_field'];
    }
  }
  return $link_to_object_fields;
}

/**
 * Return display fields with 'PID to label replacement' enabled.
 */
function islandora_solr_get_pid_to_label_replacement_fields() {
  $records = islandora_solr_get_fields('result_fields', TRUE, FALSE);
  $pid_to_label_fields = [];
  foreach ($records as $value) {
    if (isset($value['replace_pid_with_label']) && $value['replace_pid_with_label']) {
      $pid_to_label_fields[] = $value['solr_field'];
    }
  }
  return $pid_to_label_fields;
}

/**
 * Return display fields that have a length limit set.
 *
 * Returns an associative array with field names as key and truncation
 * character to use (if any) as value.
 */
function islandora_solr_get_truncate_length_fields() {
  $records = islandora_solr_get_fields('result_fields', TRUE, FALSE);
  $truncate_length_fields = [];
  foreach ($records as $value) {
    if (isset($value['maximum_length']) && intval($value['maximum_length']) > 0) {
      $truncate_length_fields[$value['solr_field']] = [
        'maximum_length' => $value['maximum_length'],
      ];
      if (isset($value['add_ellipsis'])) {
        $truncate_length_fields[$value['solr_field']]['add_ellipsis'] = $value['add_ellipsis'];
      }
      if (isset($value['wordsafe'])) {
        $truncate_length_fields[$value['solr_field']]['wordsafe'] = $value['wordsafe'];
        $truncate_length_fields[$value['solr_field']]['wordsafe_length'] = $value['wordsafe_length'];
      }
      if (isset($value['truncation_type'])) {
        $truncate_length_fields[$value['solr_field']]['truncation_type'] = $value['truncation_type'];
      }
    }
  }
  return $truncate_length_fields;
}

/**
 * Return display fields with 'link to search' enabled.
 */
function islandora_solr_get_link_to_search_fields() {
  $records = islandora_solr_get_fields('result_fields', TRUE, FALSE);
  $link_to_search_fields = [];
  foreach ($records as $value) {
    if ($value['link_to_search']) {
      $link_to_search_fields[] = $value['solr_field'];
    }
  }
  return $link_to_search_fields;
}

/**
 * Return display fields with 'date format' enabled.
 */
function islandora_solr_get_date_format_fields() {
  $records = islandora_solr_get_fields('result_fields', TRUE, FALSE);
  $date_format = [];
  foreach ($records as $value) {
    if (isset($value['date_format']) && !empty($value['date_format'])) {
      $date_format[$value['solr_field']] = $value['date_format'];
    }
  }
  return $date_format;
}

/**
 * Return facet fields with range enabled.
 */
function islandora_solr_get_range_facets() {
  $records = islandora_solr_get_fields('facet_fields', TRUE, FALSE);
  $range_facets = [];
  foreach ($records as $value) {
    if (isset($value['range_facet_select']) && $value['range_facet_select']) {
      $range_facets[] = $value;
    }
  }
  return $range_facets;
}

/**
 * Return non-range facets with date formatting enabled.
 */
function islandora_solr_get_date_format_facets() {
  $records = islandora_solr_get_fields('facet_fields', TRUE, FALSE);
  $date_format = [];
  foreach ($records as $values) {
    if (!isset($values['range_facet_select']) && isset($values['date_facet_format'])) {
      $date_format[$values['solr_field']] = $values;
    }
  }
  return $date_format;
}

/**
 * Get the sort string for a collection.
 *
 * @param string $pid
 *   The PID of the collection.
 * @param bool $use_fallbacks
 *   Whether or not to attempt to use collection sort fallbacks.
 *
 * @return string|null
 *   The sort string for this collection, or NULL if none exist.
 */
function islandora_solr_get_collection_sort_string($pid, $use_fallbacks = FALSE) {
  // Try to get the collection sort entry for this collection, if enabled.
  if (\Drupal::config('islandora_solr.settings')->get('islandora_solr_individual_collection_sorting')) {
    $query = db_select('islandora_solr_collection_sort_strings', 'ss')
      ->fields('ss', ['sort_string'])
      ->condition('collection_pid', $pid, '=')
      ->execute()
      ->fetchField();

    if (is_string($query)) {
      return $query;
    }
  }

  // Fall back, if wanted.
  if ($use_fallbacks) {
    $sort = \Drupal::config('islandora_solr.settings')->get('islandora_solr_collection_sort');
    if (!empty($sort)) {
      return $sort;
    }
    $sort = \Drupal::config('islandora_solr.settings')->get('islandora_solr_base_sort');
    if (!empty($sort)) {
      return $sort;
    }
  }
  // If all else fails, just don't set it at all.
  return NULL;
}

/**
 * Sets or updates the Solr sort string for a collection.
 *
 * @param string $pid
 *   The collection PID.
 * @param string $string
 *   The string to set it to.
 */
function islandora_solr_set_collection_sort_string($pid, $string) {
  $current = islandora_solr_get_collection_sort_string($pid);
  $message_args = [
    '@collection' => $pid,
    '@sort_string' => $string,
  ];
  // If the current sort string is NULL, then the PID wasn't found in the
  // database.
  if ($current !== $string) {
    if (!empty($string)) {
      db_merge('islandora_solr_collection_sort_strings')
        ->key(['collection_pid' => $pid])
        ->fields([
          'collection_pid' => $pid,
          'sort_string' => $string,
        ])
        ->execute();
      drupal_set_message(t('Sort string for @collection set to "@sort_string".', $message_args));
    }
    // In the case where the new string is empty, drop this entry so we can fall
    // back to global defaults.
    else {
      db_delete('islandora_solr_collection_sort_strings')
        ->condition('collection_pid', $pid)
        ->execute();
      drupal_set_message(t('Removed sort string for collection @collection.', $message_args));
    }
  }
  else {
    drupal_set_message(t('No change was made to the sort string for collection @collection.', $message_args));
  }
}
