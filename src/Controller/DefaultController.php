<?php

 /**
 * @file
 * Contains \Drupal\islandora_solr\Controller\DefaultController.
 */

namespace Drupal\islandora_solr\Controller;

use Drupal\Core\Controller\ControllerBase;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use  Drupal\islandora_solr\IslandoraSolrQueryProcessor;

/**
 * Default controller for the islandora_solr module.
 */
class DefaultController extends ControllerBase {

  /**
   * Page callback: Islandora Solr.
   *
   * Gathers url parameters, and calls the query builder, which prepares the query
   * based on the admin settings and url values.
   * Finds the right display and calls the IslandoraSolrRestuls class to build the
   * display, which it returns to the page.
   *
   * @global IslandoraSolrQueryProcessor $_islandora_solr_queryclass
   *   The IslandoraSolrQueryProcessor object which includes the current query
   *   settings and the raw Solr results.
   *
   * @param string $query
   *   The query string.
   *
   * @return string
   *   A rendered Solr display
   */
  public function islandora_solr($query = NULL, $params = NULL) {
    global $_islandora_solr_queryclass;
    // @FIXME
    // The Assets API has totally changed. CSS, JavaScript, and libraries are now
    // attached directly to render arrays using the #attached property.
    //
    //
    // @see https://www.drupal.org/node/2169605
    // @see https://www.drupal.org/node/2408597
    // drupal_add_css(drupal_get_path('module', 'islandora_solr') . '/css/islandora_solr.theme.css');


    // Url parameters.
    if ($params === NULL) {
      $params = $_GET;
    }
    // Get profiles.
    $primary_profiles = \Drupal::moduleHandler()->invokeAll('islandora_solr_primary_display');
    $secondary_profiles = \Drupal::moduleHandler()->invokeAll('islandora_solr_secondary_display');

    // Get the preferred display profile.
    // Order:
    // - $_GET['display'].
    // - The default primary display profile.
    // - Third choice is the base IslandoraSolrResults.
    $enabled_profiles = [];
    // Get enabled displays.
    $primary_display_array = \Drupal::config('islandora_solr.settings')->get('islandora_solr_primary_display_table');
    // If it's set, we take these values.
    if (isset($primary_display_array['enabled'])) {
      foreach ($primary_display_array['enabled'] as $key => $value) {
        if ($key === $value) {
          $enabled_profiles[] = $key;
        }
      }
    }
    // Set primary display.
    // Check if display param is an valid, enabled profile; otherwise, show
    // default.
    if (isset($params['display']) && in_array($params['display'], $enabled_profiles)) {
      $islandora_solr_primary_display = $params['display'];
    }
    else {
      $islandora_solr_primary_display = \Drupal::config('islandora_solr.settings')->get('islandora_solr_primary_display');
      // Unset invalid parameter.
      unset($params['display']);
    }
    $params['islandora_solr_search_navigation'] = \Drupal::config('islandora_solr.settings')->get('islandora_solr_search_navigation');

    // !!! Set the global variable. !!!
    $_islandora_solr_queryclass = new IslandoraSolrQueryProcessor();

    // Build and execute Apache Solr query.
    $_islandora_solr_queryclass->buildAndExecuteQuery($query, $params);

    if (empty($_islandora_solr_queryclass->islandoraSolrResult)) {
      return t('Error searching Solr index.');
    }

    // TODO: Also filter secondary displays against those checked in the
    // configuration options.
    if (isset($params['solr_profile']) && isset($secondary_profiles[$params['solr_profile']])) {
      $profile = $secondary_profiles[$_GET['solr_profile']];
    }
    elseif (isset($primary_profiles[$islandora_solr_primary_display])) {
      $profile = $primary_profiles[$islandora_solr_primary_display];
    }
    else {
      drupal_set_message(\Drupal\Component\Utility\Html::escape(t('There is an error in the Solr search configuration: the display profile is not found.')), 'error');
      $profile = $primary_profiles['default'];
    }

    if (isset($profile['file'])) {
      // Include the file for the display profile.
      require_once drupal_get_path('module', $profile['module']) . '/' . $profile['file'];
    }

    // Get display class and function from current display.
    $solr_class = $profile['class'];
    $solr_function = $profile['function'];

    // Check if the display's class exists.
    $use_default_display = TRUE;
    if (class_exists($solr_class)) {
      $implementation = new $solr_class();
      // Check if the display's method exists.
      if (method_exists($implementation, $solr_function)) {
        // Implement results.
        $output = $implementation->$solr_function($_islandora_solr_queryclass);
        $use_default_display = FALSE;
      }
    }

    // Class and method could not be found, so use default.
    if ($use_default_display) {
      $results_class = new IslandoraSolrResults();
      $output = $results_class->displayResults($_islandora_solr_queryclass);
    }

    // Debug dump.
    if (\Drupal::config('islandora_solr.settings')->get('islandora_solr_debug_mode')) {
      $message = t('Parameters: <br /><pre>!debug</pre>', [
        '!debug' => print_r($_islandora_solr_queryclass->solrParams, TRUE)
        ]);
      drupal_set_message(\Drupal\Component\Utility\Xss::filter($message, [
        'pre',
        'br',
      ]), 'status');
    }
    return $output;
  }

  /**
   * Admin autocomplete callback which returns solr fields from Luke.
   */
  public function _islandora_solr_autocomplete_luke(Request $request) {
    module_load_include('inc', 'islandora_solr', 'includes/luke');
    $string = $request->query->get('q');
    $luke = islandora_solr_get_luke();
    $result = [];
    foreach ($luke['fields'] as $term => $value) {
      if (stripos($term, $string) !== FALSE) {
        // Search case insensitive, but keep the case on replace.
        $term_str = preg_replace("/$string/i", "<strong>\$0</strong>", $term);

        // Add strong elements to highlight the found string.
        $result[] = [
          'label' => $term_str . '<strong style="position: absolute; right: 5px;">(' . $value['type'] . ')</strong>',
          'value' => $term,
        ];
      }
    }
    // Sort alphabetically.
    sort($result);

    return new JsonResponse($result);
  }

}
