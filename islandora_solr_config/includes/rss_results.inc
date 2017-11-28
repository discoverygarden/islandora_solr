<?php

/**
 * @file
 * Contains methods to search Solr and display results.
 */

use Drupal\Core\Url;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Template\Attribute;

use Drupal\islandora_solr\IslandoraSolrResults;

/**
 * Extention of IslandoraSolrResults for templating purposes.
 *
 * This overrides the displayResults function to provide an alternate display
 * type.
 */
class IslandoraSolrResultsRSS extends IslandoraSolrResults {

  /**
   * Outputs results basically in the normal way.
   *
   * Thumbnails pulled from the Fedora repository.
   *
   * @param object $islandora_solr_query
   *   A solr query object.
   * @param string $title
   *   Title for the RSS feed.
   */
  public function printRss($islandora_solr_query, $title = "Search Results") {
    header('Content-Type: application/rss+xml; charset=utf-8');

    // Get raw results.
    $solr_result = $islandora_solr_query->islandoraSolrResult;

    // All results.
    $docs = $solr_result['response']['objects'];

    // Loop over results.
    $items = NULL;
    foreach ($docs as $doc) {
      // Turn arrays into strings.
      foreach ($doc['solr_doc'] as $key => $value) {
        if (is_array($value)) {
          // Turn array into comma separated string and trim.
          $doc[$key] = trim(implode(', ', $value));
        }
        else {
          // Give it a trim.
          $doc[$key] = trim($value);
        }
      }
      // Get the variables for the <item> element.
      $item = $this->rssItem($doc);
      // Hook alter to change the rssItem before formatting.
      \Drupal::moduleHandler()->alter('islandora_solr_search_rss_item', $item, $doc);

      // Render rss item.
      $rendered_item = $this->formatRssItem($item['title'], $item['link'], $item['description'], $item['items']);
      // ... allow it to be altered...
      \Drupal::moduleHandler()->alter('islandora_solr_config_rss_item_post_render', $rendered_item, $doc);

      // ... and add to items string.
      $items .= "$rendered_item\n";
    }

    // Query search terms:
    $query = $islandora_solr_query->solrQuery;

    // Get the variables for the <channel> element.
    $channel = $this->rssChannel($query);
    $rss_attributes = ['version' => '2.0'];
    \Drupal::moduleHandler()->alter('islandora_solr_config_rss_root_element_attributes', $rss_attributes, $channel, $items);

    // Give the results clean variable names.
    $title = $channel['title'];
    $url = $channel['url'];
    $description = $channel['description'];
    $langcode = $channel['langcode'];
    $args = $channel['args'];
    $rss_attributes = new Attribute($rss_attributes);
    $output = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
    $output .= '<rss' . $rss_attributes . ">\n";
    $output .= $this->formatRssChannel($title, $url, $description, $items, $langcode, $args);
    $output .= "</rss>\n";

    print $output;
    exit;
  }

  /**
   * Function for setting the values of the <item> elements for the RSS display.
   *
   * @tutorial http://feed2.w3.org/docs/rss2.html#hrelementsOfLtitemgt
   *
   * @return array
   *   variable that holds all values to be rendered into <item> elements
   */
  public function rssItem($doc) {
    // Set variables.
    global $base_url;

    // Get variables.
    $rss_item = \Drupal::config('islandora_solr_config.settings')->get('islandora_solr_config_rss_item');

    // Object url.
    $object_url = Url::fromRoute('islandora.view_object', ['object' => $doc['PID']], ['absolute' => TRUE])->toString();
    // Enclosure file url (thumbnail by default).
    $dsid = ($rss_item['enclosure_dsid'] && isset($rss_item['enclosure_dsid'])) ? $rss_item['enclosure_dsid'] : 'TN';
    $doc['datastreams'] = isset($doc['datastreams']) ? $doc['datastreams'] : [];
    $is_datastream = in_array($dsid, $doc['datastreams']);

    if ($is_datastream) {
      $enclosure_url = $object_url . '/datastream/' . $dsid;
    }

    $rss_source = \Drupal::config('system.site')->get('name');

    // Set the variables to be rendered as elements in the <item> element.
    $result = [];
    $result['title'] = ($rss_item['title'] && isset($doc[$rss_item['title']])) ? $doc[$rss_item['title']] : '';
    $result['link'] = $object_url;
    $result['description'] = ($rss_item['description'] && isset($doc[$rss_item['description']])) ? $doc[$rss_item['description']] : '';
    $result['items'] = [
      [
        'key' => 'author',
        'value' => ($rss_item['author'] && isset($doc[$rss_item['author']])) ? $doc[$rss_item['author']] : '',
      ],
      [
        'key' => 'guid',
        'value' => $doc['PID'],
        'attributes' => ['isPermaLink' => 'false'],
      ],
      [
        'key' => 'pubDate',
        'value' => ($rss_item['pubDate'] && isset($doc[$rss_item['pubDate']])) ? $doc[$rss_item['pubDate']] : '',
      ],
      [
        'key' => 'category',
        'value' => ($rss_item['category'] && isset($doc[$rss_item['category']])) ? $doc[$rss_item['category']] : '',
      ],
      [
        'key' => 'comments',
        'value' => '',
      ],
      [
        'key' => 'source',
        'value' => $rss_source,
        'attributes' => ['url' => $base_url],
      ],
    ];

    if ($is_datastream) {
      $result['items'][] = [
        'key' => 'enclosure',
        'value' => '',
        'attributes' => [
          'url' => $enclosure_url,
          'length' => '',
          'type' => '',
        ],
      ];
    }

    return $result;
  }

  /**
   * Function to set values of the <channel> elements for the RSS display.
   *
   * @tutorial http://feed2.w3.org/docs/rss2.html#requiredChannelElements
   *
   * @return array
   *   variable that holds all values to be rendered into <channel> elements
   */
  public function rssChannel($query) {
    // Set variables.
    global $base_url;
    $rss_channel = \Drupal::config('islandora_solr_config.settings')->get('islandora_solr_config_rss_channel');

    // Set the variables to be rendered as elements in the <channel> element.
    $result = [];
    $result['title'] = t('@site_name aggregator', ['@site_name' => \Drupal::config('system.site')->get('name')]);

    $result['url'] = $base_url;
    $result['description'] = t('Aggregated search results of: @query', ['@query' => $query]);
    $result['langcode'] = NULL;
    $result['args'] = [
      [
        'key' => 'copyright',
        'value' => $rss_channel['copyright'],
      ],
      [
        'key' => 'managingEditor',
        'value' => $rss_channel['managingEditor'],
      ],
      [
        'key' => 'webMaster',
        'value' => $rss_channel['webMaster'],
      ],
    ];
    return $result;
  }

  /**
   * Format an RSS item.
   *
   * @see https://api.drupal.org/api/drupal/includes%21common.inc/function/format_rss_item/7.x
   */
  public function formatRssItem($title, $link, $description, $args = []) {
    $output = "<item>\n";
    $output .= ' <title>' . SafeMarkup::checkPlain($title) . "</title>\n";
    $output .= ' <link>' . check_url($link) . "</link>\n";
    $output .= ' <description>' . SafeMarkup::checkPlain($description) . "</description>\n";
    $output .= $this->formatXmlElements($args);
    $output .= "</item>\n";
    return $output;
  }

  /**
   * Print an XML element.
   *
   * @see https://api.drupal.org/api/drupal/includes%21common.inc/function/format_xml_elements/7.x
   */
  public function formatXmlElements($array) {
    $output = '';
    foreach ($array as $key => $value) {
      if (is_numeric($key)) {
        if ($value['key']) {
          $output .= ' <' . $value['key'];
          if (isset($value['attributes']) && is_array($value['attributes'])) {
            $attributes = new Attribute($value['attributes']);
            $output .= "$attributes";
          }
          if (isset($value['value']) && $value['value'] != '') {
            $output .= '>' . (is_array($value['value']) ? $this->formatXmlElements($value['value']) : (!empty($value['encoded']) ? $value['value'] : SafeMarkup::checkPlain($value['value']))) . '</' . $value['key'] . ">\n";
          }
          else {
            $output .= " />\n";
          }
        }
      }
      else {
        $output .= ' <' . $key . '>' . (is_array($value) ? $this->formatXmlElements($value) : SafeMarkup::checkPlain($value)) . "</{<span class=\"php-variable\">$key</span>}>\n";
      }
    }
    return $output;
  }

  /**
   * Format RSS channel.
   *
   * @see https://api.drupal.org/api/drupal/includes%21common.inc/function/format_rss_channel/7.x
   */
  public function formatRssChannel($title, $link, $description, $items, $langcode = NULL, $args = []) {
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $output = "<channel>\n";
    $output .= ' <title>' . SafeMarkup::checkPlain($title) . "</title>\n";
    $output .= ' <link>' . check_url($link) . "</link>\n";

    // The RSS 2.0 "spec" doesn't indicate HTML can be used in the description.
    // We strip all HTML tags, but need to prevent double encoding from properly
    // escaped source data (such as &amp becoming &amp;amp;).
    $output .= ' <description>' . SafeMarkup::checkPlain(Html::escape(strip_tags($description))) . "</description>\n";
    $output .= ' <language>' . SafeMarkup::checkPlain($langcode) . "</language>\n";
    $output .= $this->formatXmlElements($args);
    $output .= $items;
    $output .= "</channel>\n";
    return $output;
  }

}
