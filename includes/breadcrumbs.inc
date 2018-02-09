<?php

/**
 * @file
 * Breadcrumb functions.
 */

use Drupal\Component\Utility\Html;
use Drupal\Core\Link;

use Drupal\islandora_solr\IslandoraSolrQueryProcessor;

/**
 * Implements callback_islandora_breadcrumbs_backends.
 */
function islandora_solr_islandora_breadcrumbs_backends_callable($object) {
  $breadcrumbs = islandora_solr_get_breadcrumbs_recursive($object->id);
  // Pop off the current object.
  array_pop($breadcrumbs);
  return $breadcrumbs;
}

/**
 * Builds an array of drupal links for use in breadcrumbs.
 *
 * @todo Make fully recursive...
 *
 * @param string $pid
 *   The object id whose parent will be fetched for the next link.
 * @param array $context
 *   An associative array of context for internal use when recursing. Currently
 *   only used to track a single value:
 *   - level: The number of child-parent relationships to follow. Defaults to
 *     10.
 *
 * @return array
 *   An array of links representing the breadcrumb trail, "root" first.
 */
function islandora_solr_get_breadcrumbs_recursive($pid, array &$context = NULL) {
  if ($context === NULL) {
    $context['level'] = 10;
  }
  if (!isset($context['root'])) {
    $root = \Drupal::config('islandora.settings')->get('islandora_repository_pid');
    $context['root'] = $root;
  }
  if ($pid == $context['root']) {
    $menu_link_manager = \Drupal::service('plugin.manager.menu.link');
    $islandora_links = $menu_link_manager->loadLinksByRoute('islandora.view_default_object');
    $islandora_link = reset($islandora_links);
    return [
      Link::createFromRoute(t('Home'), '<front>'),
      Link::createFromRoute($islandora_link->getTitle(), 'islandora.view_default_object'),
    ];
  }
  else {
    $parents = islandora_solr_get_breadcrumb_parent($pid);
    if ($parents) {
      $this_label = $parents['label'];
      $parent = $parents['PID'];
    }
    if (empty($this_label)) {
      $this_label = t('-');
    }
    if (isset($parent)) {
      // We found a parent.
      $context['level']--;
      return array_merge(islandora_solr_get_breadcrumbs_recursive($parent, $context), [
        Link::createFromRoute($this_label, 'islandora.view_object', ['object' => $pid]),
      ]);
    }
    else {
      // Add an non-link, as we don't know how to get back to the root, and
      // render the last two links and break (on the next pass).
      return array_merge(
        islandora_solr_get_breadcrumbs_recursive($context['root'], $context),
        ['...']
      );
    }
  }
}

/**
 * Get the parent object using the configured Solr fields.
 *
 * @param string $pid
 *   The pid of the object.
 *
 * @return array
 *   Array with the parent pid and current object label.
 */
function islandora_solr_get_breadcrumb_parent($pid) {
  module_load_include('inc', 'islandora_solr', 'includes/query_processor');
  $solr_build = new IslandoraSolrQueryProcessor();
  $solr_query = "PID:(" . islandora_solr_lesser_escape($pid) . " OR " .
    islandora_solr_lesser_escape("info:fedora/" . $pid) . ")";
  $parent_fields = preg_split("/\\r\\n|\\n|\\r/", \Drupal::config('islandora_solr.settings')->get('islandora_solr_breadcrumbs_parent_fields'), -1, PREG_SPLIT_NO_EMPTY);
  $solr_params = [
    'fl' => implode(",", $parent_fields) . ',fgs_label_s,PID',
    'hl' => 'false',
    'facet' => 'false',
  ];
  $solr_build->buildQuery($solr_query, $solr_params);
  $solr_build->solrParams = array_replace_recursive($solr_build->solrParams, $solr_params);
  $solr_build->solrParams = islandora_solr_remove_base_filters($solr_build->solrParams);
  $solr_build->solrParams = islandora_solr_clean_compound_filters($solr_build->solrParams);

  try {
    $solr_build->executeQuery(FALSE);
    $results = (array) $solr_build->islandoraSolrResult['response']['objects'];
  }
  catch (Exception $e) {
    $results = [];
    drupal_set_message(Html::escape(t('Error searching Solr index')) . ' ' . $e->getMessage(), 'error', FALSE);
  }

  $find_solr_value = function ($o, $field) {
    if (isset($o[$field])) {
      return $o[$field];
    }
    elseif (isset($o['solr_doc'][$field])) {
      return $o['solr_doc'][$field];
    }
    else {
      return NULL;
    }
  };

  if ($results) {
    foreach ($parent_fields as $field) {
      $field_value = $find_solr_value($results[0], $field);
      if ($field_value) {
        if (is_array($field_value)) {
          $parent_pid = reset($field_value);
        }
        else {
          $parent_pid = $field_value;
        }
        // Strip prefix if there is one.
        $parent_pid = str_replace("info:fedora/", "", $parent_pid);
        return [
          'PID' => $parent_pid,
          'label' => $find_solr_value($results[0], 'fgs_label_s'),
        ];
      }
    }
  }
  return [];
}

/**
 * To trace breadcrumbs isConstituent through Solr we need to remove filters.
 *
 * @param array $params
 *   The Solr params array.
 *
 * @return array
 *   The $params array minus any base filters.
 */
function islandora_solr_clean_compound_filters(array $params) {
  if (\Drupal::moduleHandler()->moduleExists('islandora_compound_object') && is_array($params) && isset($params['fq'])) {
    $compound_filters = \Drupal::config('islandora_compound_object.settings')->get('islandora_compound_object_solr_fq');
    $params['fq'] = array_diff($params['fq'], $compound_filters);
  }
  return $params;
}
