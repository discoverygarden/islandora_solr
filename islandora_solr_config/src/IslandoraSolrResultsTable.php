<?php
namespace Drupal\islandora_solr_config;

/**
 * Extension of IslandoraSolrResults to create an alternative display type.
 */
class IslandoraSolrResultsTable extends IslandoraSolrResults {

  /**
   * Renders the Solr results as a table.
   *
   * @see IslandoraSolrResults::displayResults()
   *
   * @param array $solr_results
   *   The raw Solr results from
   *   IslandoraSolrQueryProcessor::islandoraSolrResult.
   *
   * @return string
   *   Rendered Solr results
   */
  public function printResults($solr_results) {
    $mod_path = drupal_get_path('module', 'islandora_solr_config');
    // @FIXME
// The Assets API has totally changed. CSS, JavaScript, and libraries are now
// attached directly to render arrays using the #attached property.
// 
// 
// @see https://www.drupal.org/node/2169605
// @see https://www.drupal.org/node/2408597
// drupal_add_css("$mod_path/css/islandora_solr_config.theme.css");


    $solr_results = islandora_solr_prepare_solr_results($solr_results);
    $object_results = $solr_results['response']['objects'];
    $object_results = islandora_solr_prepare_solr_doc($object_results);

    $record_start = (int) $solr_results['response']['start'];
    $fields = $this->resultFieldArray;
    $empty = array();
    foreach ($fields as $field => $label) {
      $empty[$field] = TRUE;
    }
    $rows = array();
    foreach ($object_results as $key => $object_result) {
      $row = array();
      $doc = $object_result['solr_doc'];
      // @FIXME
// // @FIXME
// // This looks like another module's variable. You'll need to rewrite this call
// // to ensure that it uses the correct configuration object.
// if (variable_get('islandora_solr_table_profile_display_row_no', 1) == 1) {
//         $row['#'] = l(($key + 1), $object_result['object_url'], array('query' => $object_result['object_url_params']));
//       }

      foreach ($fields as $field => $field_label) {
        if (isset($doc[$field]['value'])) {
          $value = $doc[$field]['value'];
          $row[$field] = $value;
          $empty[$field] = FALSE;
        }
        else {
          $row[$field] = NULL;
        }
      }
      $rows[] = $row;
    }
    $header = array();
    // @FIXME
// // @FIXME
// // This looks like another module's variable. You'll need to rewrite this call
// // to ensure that it uses the correct configuration object.
// if (variable_get('islandora_solr_table_profile_display_row_no', 1) == 1) {
//       $header['#'] = '#';
//     }

    $header += $fields;

    // Filter empty columns.
    foreach ($empty as $field => $bool) {
      if ($bool == TRUE) {
        unset($header[$field]);
        foreach ($rows as $key => $row) {
          unset($rows[$key][$field]);
        }
      }
    }
    // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
// 
// 
// @see https://www.drupal.org/node/2195739
// $output = theme('islandora_solr_table', array('header' => $header, 'rows' => $rows));

    return $output;
  }

}
