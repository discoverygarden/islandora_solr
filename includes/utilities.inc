<?php

/**
 * @file
 * Miscellaneous helper functions.
 */

use Drupal\islandora_solr\IslandoraSolrQueryProcessor;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Initialize a pager for theme('pager') without running an SQL query.
 *
 * The nuts and bolts of this function are borrowed from z3950.module
 * and also apachesolr.module.
 *
 * @param int $total
 *   The total number of items found.
 * @param int $limit
 *   The number of items you will display per page.
 * @param int $element
 *   An optional integer to distinguish between multiple pagers on one page.
 *
 * @return int
 *   The current page for $element. 0 by default if $_GET['page'] is empty.
 */
function islandora_solr_pager_init($total, $limit = 10, $element = 0) {
  global $pager_page_array, $pager_total, $pager_total_items;
  $page = isset($_GET['page']) ? $_GET['page'] : '';

  // Convert comma-separated $page to an array, used by other functions.
  $pager_page_array = explode(',', $page);

  // We calculate the total of pages as ceil(items / limit).
  $pager_total_items[$element] = $total;
  $pager_total[$element] = ceil($pager_total_items[$element] / $limit);
  $pager_page_array[$element] = max(0, min((int) $pager_page_array[$element], ((int) $pager_total[$element]) - 1));

  // Return the current position.
  return $pager_page_array[$element];
}

/**
 * Restore slashes in a string.
 *
 * Can't have a slash in the URL, as it will break Drupal clean URLs.
 *
 * @param string $str
 *   An arbitrary string.
 *
 * @return string
 *   The string with any occurences of '~slsh~' and '~plus~' changed to '/' and
 *   '+' respectively... Effectively the opposite of
 *   islandora_solr_replace_slashes().
 *
 * @see islandora_solr_replace_slashes()
 */
function islandora_solr_restore_slashes($str) {
  $replacements = ["~slsh~", "~plus~"];
  $values = ["/", "+"];
  return str_replace($replacements, $values, $str);
}

/**
 * Replace slashes in a string.
 *
 * Can't have a slash in the URL, as it will break Drupal clean URLs.
 *
 * @param string $str
 *   An arbitrary string.
 *
 * @return string
 *   The string with any occurences of '/' and '+' changed to '~slsh~' and
 *   '~plus~' respectively... Effectively the opposite of
 *   islandora_solr_restore_slashes().
 *
 * @see islandora_solr_restore_slashes()
 */
function islandora_solr_replace_slashes($str) {
  $replacements = ["~slsh~", "~plus~"];
  $values = ["/", "+"];
  return str_replace($values, $replacements, $str);
}

/**
 * A better explode method allows quotes in the returned strings.
 *
 * Taken from php.net.
 *
 * @param string $delim
 *   The delimiter on which to break (',' by default).
 * @param string $str
 *   The string to break.
 * @param string $enclose
 *   An enclosing character.
 * @param bool $preserve
 *   ??? Something to do with $enclose.
 *
 * @return array
 *   An array containing the items of $str, broken apart on $delim.
 */
function islandora_solr_csv_explode($delim = ',', $str = '', $enclose = '"', $preserve = FALSE) {
  // @XXX: Is this function really necessary?
  $res_arr = [];
  $n = 0;
  $exp_enc_arr = explode($enclose, $str);
  foreach ($exp_enc_arr as $enc_item) {
    if ($n++ % 2) {
      array_push($res_arr, array_pop($res_arr) . ($preserve ? $enclose : '') . $enc_item . ($preserve ? $enclose : ''));
    }
    else {
      $exp_del_arr = explode($delim, $enc_item);
      array_push($res_arr, array_pop($res_arr) . array_shift($exp_del_arr));
      $res_arr = array_merge($res_arr, $exp_del_arr);
    }
  }
  return $res_arr;
}

/**
 * Escape a string for inclusion in a facet query term.
 *
 * Copied from Apache_Solr_Service.
 *
 * The default charset diverges from Apache_Solr_Service::escape():
 *
 * @param string $value
 *   A string to escape.
 * @param array|string $pattern
 *   A(or set of) regular expression pattern(s).
 *
 * @return string
 *   The escaped string.
 */
function islandora_solr_facet_query_escape($value, $pattern = NULL) {
  if (!$pattern) {
    $pattern = ISLANDORA_SOLR_QUERY_FACET_LUCENE_ESCAPE_REGEX_DEFAULT;
  }
  $replace = '\\\$1';
  return preg_replace($pattern, $replace, $value);
}

/**
 * Escape a string for inclusion in a query field or value.
 *
 * Copied from Apache_Solr_Service.
 *
 * Divergences from Apache_Solr_Service::escape():
 * - removed '?':
 *   - '?' is useful.
 * - removed '*':
 *   - Allows wildcard searches.
 * - added space:
 *   - Needed for spaces in fields.
 *
 * @param string $value
 *   A string to escape.
 *
 * @return string
 *   The escaped string.
 */
function islandora_solr_lesser_escape($value) {
  $pattern = '/(\/|\+|-|&&|\|\||!|\(|\)|\{|}|\[|]|\^| |~|\?|:|\\\)/';
  $replace = '\\\$1';

  return preg_replace($pattern, $replace, $value);
}

/**
 * Escape a string for inclusion in a facet.
 *
 * Copied from Apache_Solr_Service.
 *
 * Divergences from Apache_Solr_Service::escape():
 * - removed '?':
 *   - '?' is useful.
 * - removed '*':
 *   - Allows wildcard searches.
 * - added space:
 *   - Needed for spaces in fields.
 *
 * @param string $value
 *   A string to escape.
 *
 * @return string
 *   The escaped string.
 */
function islandora_solr_facet_escape($value) {
  $pattern = '/(\"|\/|\+|-|&&|\|\||!|\(|\)|\{|}|\[|]|\^| |~|\?|:|\\\)/';
  $replace = '\\\$1';

  return preg_replace($pattern, $replace, $value);
}

/**
 * Escape characters in field names of facets.
 */
function islandora_solr_escape($facets) {
  $return_facets = [];
  foreach ($facets as $facet) {
    $tmp = Unicode::substr($facet, 0, strpos($facet, ':"'));
    $tmp = islandora_solr_lesser_escape(trim($tmp));

    $return_facets[] = $tmp . Unicode::substr($facet, strpos($facet, ':"'), Unicode::strlen($facet));
  }
  return $return_facets;
}

/**
 * Turns term fields strings from the admin forms into arrays.
 *
 * Mostly used with variable_get().
 *
 * @param string $raw_field_list
 *   A string containing the list of fields to parse, with individual items
 *   separated by either a comma or newline. Each field entry may be either a
 *   field name alone, or a field name, a tilde, and then a human-readable
 *   name, so for the field "field_name":
 *   - "field_name": The field itself will be used as the "human-readable".
 *   - "field_name ~ My Cool Field": "My Cool Field" will be used as as the
 *     human-readable version.
 *
 * @return array
 *   An associative array mapping the fields to our human-readable label.
 *
 * @deprecated
 *   No longer used, due to the use of a separate database table for
 *   configuration.
 */
function islandora_solr_build_substitution_list($raw_field_list) {
  $facet_array = [];
  $facet_fields = '';
  $lines = preg_split('/[,|\n|\r]/', $raw_field_list);
  foreach ($lines as $line) {
    if ($line) {
      $line_array = explode('~', $line);
      $key = trim($line_array[0]);
      if (count($line_array) > 1) {
        $value = trim($line_array[1]);
      }
      else {
        $value = $key;
      }
      $facet_array[$key] = $value;
      if ($facet_fields) {
        $facet_fields .= ",";
      }
      $facet_fields .= $key;
    }
  }
  return $facet_array;
}

/**
 * Prepares the Solr docs of an object results array.
 *
 * Preparing the Solr doc array includes adding the label, value and class name
 * based on the Solr field name. When you created a new Solr display, it is
 * not required to use this helper function, but it can be helpful.
 *
 * @param array $object_results
 *   An array containing the object results. This includes the Solr doc array.
 *
 * @return array
 *   The original object results array, but with a modified Solr doc.
 */
function islandora_solr_prepare_solr_doc(array $object_results) {
  $fields_all = islandora_solr_get_fields('result_fields', FALSE);
  // Loop over object results.
  foreach ($object_results as $object_index => $object_result) {
    $doc = $object_result['solr_doc'];
    $rows = [];
    foreach ($doc as $field => $value) {
      if (isset($fields_all[$field])) {
        $label = $fields_all[$field];
      }
      else {
        $label = $field;
      }
      $rows[$field]['label'] = $label;
      $rows[$field]['class'] = Unicode::strtolower(preg_replace('/[^A-Za-z0-9]/', '-', $field));
      $rows[$field]['value'] = $value;
    }
    // Replace Solr doc.
    $object_results[$object_index]['solr_doc'] = $rows;
  }
  return $object_results;
}

/**
 * Prepares Solr results before rendering.
 *
 * Prepares Solr results before rendering. Applies highlighting, implodes
 * arrays and links to objects to Solr result fields.
 *
 * @param array $solr_results
 *   Array containing the Solr results which are altered trough the query
 *   processor.
 *
 * @return array
 *   Returns the same array but with prepared Solr field values.
 */
function islandora_solr_prepare_solr_results(array $solr_results) {
  module_load_include('inc', 'islandora_solr', 'includes/field_truncation');
  module_load_include('inc', 'islandora_solr', 'includes/db');

  $object_results = $solr_results['response']['objects'];
  $highlighting = isset($solr_results['highlighting']) ? $solr_results['highlighting'] : [];
  $link_to_object = islandora_solr_get_link_to_object_fields();
  $truncate_length = islandora_solr_get_truncate_length_fields();
  $link_to_search = islandora_solr_get_link_to_search_fields();
  $date_format = islandora_solr_get_date_format_fields();
  $replace_pid_with_label = islandora_solr_get_pid_to_label_replacement_fields();

  // Loop over object results.
  foreach ($object_results as $object_index => $object_result) {
    $solr_doc = $object_result['solr_doc'];
    $pid = $object_result['PID'];
    $options = ['html' => TRUE];
    if (isset($object_result['object_label'])) {
      $options['attributes']['title'] = $object_result['object_label'];
    }
    if (isset($object_result['object_url_params'])) {
      $options['query'] = $object_result['object_url_params'];
    }
    if (isset($object_result['object_url_fragment'])) {
      $options['fragment'] = $object_result['object_url_fragment'];
    }

    foreach ($solr_doc as $field => $original_value) {
      $value = (array) $original_value;

      // If value is truncated, use the truncated value separator.
      if (array_key_exists($field, $truncate_length)) {
        $separator = \Drupal::config('islandora_solr.settings')->get('islandora_solr_search_truncated_field_value_separator');

        if (empty($separator)) {
          $separator = "<br />";
        }
      }
      else {
        $separator = \Drupal::config('islandora_solr.settings')->get('islandora_solr_search_field_value_separator');

        if (empty($separator)) {
          $separator = ', ';
        }
      }

      // Date formatting.
      if (isset($date_format[$field])) {
        $map_date_format = function ($value) use ($date_format, $field) {
          return format_date(strtotime($value), 'custom', $date_format[$field], 'UTC');
        };
        $value = array_map($map_date_format, $value);
      }
      // Only apply highlighting when the date isn't formatted.
      elseif (isset($highlighting[$pid][$field])) {
        $value = $highlighting[$pid][$field];
      }
      // Replace PID with Object Label.
      if (in_array($field, $replace_pid_with_label)) {
        $query_processor = new IslandoraSolrQueryProcessor();
        $pid_to_label_mapper = function ($uri) use ($query_processor) {
          $pid = str_replace('info:fedora/', '', $uri);
          if (islandora_is_valid_pid($pid)) {
            $query_processor->buildQuery("PID:\"$pid\"");
            $query_processor->solrParams['fl'] = 'PID, ' . \Drupal::config('islandora_solr.settings')->get('islandora_solr_object_label_field');
            $query_processor->executeQuery(FALSE);

            $label = NULL;
            if (!empty($query_processor->islandoraSolrResult) && !empty($query_processor->islandoraSolrResult['response']['objects'])) {
              $label = (!empty($query_processor->islandoraSolrResult['response']['objects'][0]['object_label']) ?
                $query_processor->islandoraSolrResult['response']['objects'][0]['object_label'] : NULL);
            }
            // Fall back to islandora object if PID is not in solr.
            // eg: content models.
            else {
              if ($object = islandora_object_load($pid)) {
                if (islandora_object_access(ISLANDORA_VIEW_OBJECTS, $object)) {
                  $label = $object->label;
                }
              }
            }
          }
          return (!empty($label) ? $label : $uri);
        };
        $value = array_map($pid_to_label_mapper, (array) $value);
      }
      // Truncate value lengths before linking, avoids destroying link tags.
      if (array_key_exists($field, $truncate_length) && $truncate_length[$field]['maximum_length'] > 0 && (!isset($truncate_length[$field]['truncation_type']) || $truncate_length[$field]['truncation_type'] == 'separate_value_option')) {
        // Coder tends to not like inline anonymous functions.
        $truncate_func = function (&$val_val) use ($field, $truncate_length) {
          $tf =& $truncate_length[$field];
          $wordsafe = FALSE;
          $min_wordsafe_length = 1;

          if (isset($tf['wordsafe'])) {
            $wordsafe = $tf['wordsafe'];
            $min_wordsafe_length = $tf['wordsafe_length'];
          }

          $val_val = (string) new IslandoraSolrFieldTruncation(
            $val_val,
            $tf['maximum_length'],
            $wordsafe,
            $tf['add_ellipsis'],
            $min_wordsafe_length
          );

        };
        array_walk($value, $truncate_func);
      }
      // Truncate output based on the field rather than by value.
      if (array_key_exists($field, $truncate_length) && $truncate_length[$field]['maximum_length'] > 0 && isset($truncate_length[$field]['truncation_type']) && $truncate_length[$field]['truncation_type'] == 'whole_field_option') {
        // Handle linking and truncating together.
        $link_options = [
          'link_to_object' => in_array($field, $link_to_object),
          'link_to_search' => in_array($field, $link_to_search),
          'field' => $field,
          'url' => $object_result['object_url'],
          'options' => $options,
        ];
        $value = islandora_solr_truncate_field_display($value, $truncate_length[$field]['maximum_length'], $truncate_length[$field]['add_ellipsis'], $truncate_length[$field]['wordsafe'], $truncate_length[$field]['wordsafe_length'], $separator, $link_options);
      }
      else {
        // Add link to search.
        if (in_array($field, $link_to_search)) {
          $map_to_link = function ($original_value, $formatted_value) use ($field) {
            $solr_query = format_string('@field:"@value"', [
              '@field' => $field,
              '@value' => islandora_solr_replace_slashes(islandora_solr_lesser_escape($original_value)),
            ]);
            return Link::createFromRoute($formatted_value, 'islandora_solr.islandora_solr', ['query' => $solr_query])->toString();
          };
          $value = array_map($map_to_link, (array) $original_value, (array) $value);
        }
        elseif (in_array($field, $link_to_object)) {
          $value = Link::fromTextAndUrl(implode($separator, $value), Url::fromUri($object_result['object_url'], $options))->toString();
        }
      }
      // Implode.
      $value = is_array($value) ? implode(Xss::filter($separator, islandora_solr_get_filter_tags()), $value) : $value;
      $solr_doc[$field] = ['#markup' => $value];
    }
    // Replace Solr doc rows.
    $solr_results['response']['objects'][$object_index]['solr_doc'] = $solr_doc;
  }
  return $solr_results;
}

/**
 * Implements callback_islandora_basic_collection_query_backends().
 */
function islandora_solr_islandora_basic_collection_backend_callable($collection_object, $page, $limit) {
  module_load_include('inc', 'islandora_solr', 'includes/db');
  // XXX: We populate the global query class, to allow the sort and facet
  // blocks and the like to function.
  global $_islandora_solr_queryclass;
  $qp = new IslandoraSolrQueryProcessor();
  $_islandora_solr_queryclass = $qp;

  $qp->buildQuery("*:*", \Drupal::request()->query->all());
  $pid = $collection_object->id;
  $member_of = \Drupal::config('islandora_solr.settings')->get('islandora_solr_member_of_field');
  $member_of_collection = \Drupal::config('islandora_solr.settings')->get('islandora_solr_member_of_collection_field');
  $filters = strtr('!member_of_collection:("!iripid" OR "!pid") OR !member_of:("!iripid" OR "!pid")',
    [
      '!member_of_collection' => $member_of_collection,
      '!iripid' => "info:fedora/$pid",
      '!pid' => $pid,
      '!member_of' => $member_of,
    ]
  );
  $qp->solrParams['fq'][] = $filters;

  if (\Drupal::config('islandora_solr.settings')->get('islandora_solr_collection_result_limit_block_override')) {
    if (isset($qp->internalSolrParams['limit'])) {
      $limit = $qp->internalSolrParams['limit'];
    }
    else {
      $limit = \Drupal::config('islandora_solr.settings')->get('islandora_solr_num_of_results');
    }
  }

  $qp->solrStart = $page * $limit;
  $qp->solrLimit = $limit;

  if (\Drupal::config('islandora_solr.settings')->get('islandora_solr_collection_sort_block_override') && isset($qp->internalSolrParams['sort'])) {
    $qp->solrParams['sort'] = $qp->internalSolrParams['sort'];
  }
  else {
    $qp->solrParams['sort'] = islandora_solr_get_collection_sort_string($collection_object->id, TRUE);
  }

  $qp->executeQuery();

  $map_to_pids = function ($result) {
    return $result['PID'];
  };
  return [
    $qp->islandoraSolrResult['response']['numFound'],
    array_map($map_to_pids, $qp->islandoraSolrResult['response']['objects']),
  ];
};

/**
 * Implements callback_islandora_compound_object_query_backends().
 */
function islandora_solr_compound_object_query($pid) {
  module_load_include('inc', 'islandora_solr', 'includes/breadcrumbs');
  $solr_build = new IslandoraSolrQueryProcessor();
  $rows = 1000;
  $relationship = \Drupal::config('islandora_solr.settings')->get('islandora_solr_compound_relationship_field');
  $sequence_pattern = \Drupal::config('islandora_solr.settings')->get('islandora_solr_compound_sequence_pattern');
  $sequence_pattern = str_replace('%PID%', str_replace(':', '_', $pid), $sequence_pattern);
  $query = format_string('@field:("@pid"+OR+"info:fedora/@pid")', [
    '@field' => $relationship,
    '@pid' => $pid,
  ]);
  $params = [
    'limit' => $rows,
    'rows' => $rows,
    'fl' => format_string('PID, !seq', [
      '!seq' => $sequence_pattern,
    ]),
  ];
  // Page to start on.
  $start = -1;
  // Total results holder.
  $total = NULL;
  // For cumulative storage of constituents.
  $constituents = [];
  // Final output.
  $output = [];
  $solr_build->buildQuery($query, $params);
  $solr_build->solrParams['facet'] = $solr_build->solrParams['hl'] = 'false';
  $solr_build->solrParams = islandora_solr_clean_compound_filters($solr_build->solrParams);
  // Loop in case there are lots.
  do {
    $start += 1;
    $solr_build->solrStart = $start * $rows;
    $solr_build->solrLimit = $rows;
    try {
      $solr_build->executeQuery(FALSE);
      $results = (array) $solr_build->islandoraSolrResult['response']['objects'];
      $constituents = array_merge($constituents, $results);
    }
    catch (Exception $e) {
      drupal_set_message(check_plain(t('Error searching Solr index')) . ' ' . $e->getMessage(), 'error', FALSE);
      break;
    }
    if (is_null($total) && isset($solr_build->islandoraSolrResult['response']['numFound'])) {
      $total = $solr_build->islandoraSolrResult['response']['numFound'];
    }
  } while ($total > (($start * $rows) + $rows));
  if (count($constituents) > 0) {
    $sort = function ($a, $b) use ($sequence_pattern) {
      $a = $a['solr_doc'][$sequence_pattern];
      if (is_array($a)) {
        $a = reset($a);
      }
      $a = intval($a);
      $b = $b['solr_doc'][$sequence_pattern];
      if (is_array($b)) {
        $b = reset($b);
      }
      $b = intval($b);
      if ($a === $b) {
        return 0;
      }
      if (empty($a)) {
        return 1;
      }
      if (empty($b)) {
        return -1;
      }
      return $a - $b;
    };
    uasort($constituents, $sort);
    foreach ($constituents as $result) {
      $sequence = $result['solr_doc'][$sequence_pattern];
      if (is_array($sequence)) {
        $sequence = reset($sequence);
      }
      $output[$result['PID']] = [
        'pid' => $result['PID'],
        'title' => $result['object_label'],
        'seq' => $sequence,
      ];
    }
  }
  return $output;
}

/**
 * Truncate and link the field display based on entire field result(s).
 *
 * @param array $display_values
 *   An array of the values that are to be processed for truncation.
 * @param int $max_length
 *   The maxium length of characters to display before truncating results.
 * @param bool $add_ellipsis
 *   Boolean to enable the display of an ellispsis.
 * @param bool $word_safe
 *   Boolean to enable word safe in truncate_utf8.
 * @param int $wordsafe_length
 *   Min wordsafe length.
 * @param string $separator
 *   A separator to use for display output.
 * @param array|null $link_options
 *   (Optional) An array of link options including:
 *   - 'link_to_object' : (bool) Include link to object.
 *   - 'link_to_search' : (bool) Include link to search results.
 *   - 'field' : (SOLR Result Field) Result field.
 *   - 'url' : (SOLR object results object_url) objects result url.
 *   - 'options' : link to object l() options param.
 *
 * @return string
 *   The updated display values.
 */
function islandora_solr_truncate_field_display(array $display_values, $max_length, $add_ellipsis, $word_safe, $wordsafe_length, $separator, $link_options = NULL) {
  module_load_include('inc', 'islandora_solr', 'includes/field_truncation');
  $updated_display_values = $display_values;
  $separator = Xss::filter($separator, islandora_solr_get_filter_tags());

  if (count($updated_display_values) > 0) {
    $value_is_truncated = FALSE;

    // Build two arrays for display, one filtered by size (max_values)
    // and one with all the data.
    $truncated_list = [];
    $character_output_count = 0;
    foreach ($updated_display_values as $current_value) {
      if ($character_output_count + Unicode::strlen($current_value) <= $max_length) {
        $truncated_list[] = $current_value;
        $character_output_count += Unicode::strlen($current_value);
      }
      elseif ($character_output_count + Unicode::strlen($current_value) > $max_length) {
        $value_is_truncated = TRUE;
        $truncation_length = $max_length - $character_output_count;
        // Force the display of the full ellipsis.
        if ($add_ellipsis) {
          $truncation_length = max($truncation_length, Unicode::strlen(t('...')));
        }

        $truncated_list[] = (string) new IslandoraSolrFieldTruncation(
          $current_value,
          $truncation_length,
          $word_safe,
          $add_ellipsis,
          $wordsafe_length
        );

        break;
      }
    }

    $original_value = implode($separator, $updated_display_values);
    $truncated_value = implode($separator, $truncated_list);
    if ($link_options !== NULL) {
      if (isset($link_options['link_to_object']) && $link_options['link_to_object'] == TRUE) {
        $original_value = Link::fromTextAndUrl($original_value, Url::fromUri($link_options['url'], $link_options['options']))->toString();
        $truncated_value = Link::fromTextAndUrl($truncated_value, Url::fromUri($link_options['url'], $link_options['options']))->toString();
      }
      elseif (isset($link_options['link_to_search']) && $link_options['link_to_search'] == TRUE) {
        $solr_query = format_string('@field:"@value"', [
          '@field' => $link_options['field'],
          '@value' => islandora_solr_lesser_escape($original_value),
        ]);
        $original_value = Link::createFromRoute($original_value, 'islandora_solr.islandora_solr', ['query' => $solr_query])->toString();
        $truncated_value = Link::createFromRoute($truncated_value, 'islandora_solr.islandora_solr', ['query' => $solr_query])->toString();
      }
    }

    if ($value_is_truncated) {
      $updated_display_values
        = "<span class='toggle-wrapper'>" .
        t("<span>@value <br /><a href='#' class='toggler'>Show more</a></span>", [
          '@value' => $truncated_value,
        ]);
      $updated_display_values .= t("<span>@original_value <br /><a href='#' class='toggler'>Show less</a></span>", [
        '@original_value' => $original_value,
      ]);
    }
    else {
      $updated_display_values
        = "<span class='toggle-wrapper'>" .
        t("<span>@value <br /></span>", [
          '@value' => $truncated_value,
        ]);
    }
    $updated_display_values .= '</span>';

  }
  else {
    $updated_display_values = '';
  }
  return $updated_display_values;
};

/**
 * Check the field type against the user-specified list of date field types.
 *
 * @param string $solr_field
 *   The Solr field name.
 *
 * @return bool
 *   Whether the field matches the date field types.
 */
function islandora_solr_is_date_field($solr_field) {
  $date_types = [
    'org.apache.solr.schema.DateField',
    'org.apache.solr.schema.TrieDateField',
    'org.apache.solr.schema.DatePointField',
    'org.apache.solr.schema.DateRangeField',
  ];
  return islandora_solr_is_typed_field($solr_field, $date_types);
}

/**
 * Check the field type against boolean-type fields.
 *
 * @param string $solr_field
 *   The Solr field name.
 *
 * @return bool
 *   Whether the field is a boolean type.
 */
function islandora_solr_is_boolean_field($solr_field) {
  $bool_types = [
    'org.apache.solr.schema.BoolField',
  ];
  return islandora_solr_is_typed_field($solr_field, $bool_types);
}

/**
 * Check the field type against an array of types.
 *
 * @param string $solr_field
 *   The Solr field name.
 * @param array $types
 *   A list of Solr field type classes to check against.
 *
 * @return bool
 *   Whether the field type is in the provided list.
 */
function islandora_solr_is_typed_field($solr_field, array $types) {
  $luke_result = islandora_solr_get_luke(NULL, $solr_field);
  $type = (isset($luke_result['fields'][$solr_field]['type']) ? $luke_result['fields'][$solr_field]['type'] : FALSE);
  if ($type) {
    $class = islandora_solr_get_type_class($type);
    return in_array($class, $types);
  }
  return FALSE;
}

/**
 * Removes the base filters from the Solr params if they exist.
 *
 * @param array $params
 *   The Solr params array.
 *
 * @return array
 *   The $params array minus any base filters.
 */
function islandora_solr_remove_base_filters(array $params) {
  if (isset($params['fq'])) {
    $base_filters = preg_split("/\\r\\n|\\n|\\r/", \Drupal::config('islandora_solr.settings')->get('islandora_solr_base_filter'), -1, PREG_SPLIT_NO_EMPTY);
    $params['fq'] = array_diff($params['fq'], $base_filters);
  }
  return $params;
}

/**
 * Return an array of html tags to filter against when preparing solr display.
 *
 * @return array
 *   The array of html tags being filtered against.
 */
function islandora_solr_get_filter_tags() {
  $filter_tags = [
    'a',
    'em',
    'strong',
    'cite',
    'blockquote',
    'code',
    'ul',
    'ol',
    'li',
    'dl',
    'dt',
    'dd',
    'br',
    'span',
    'div',
  ];

  return $filter_tags;
}

/**
 * Get the Solr config from the given installation.
 *
 * Seem to get the Solr config a couple places... We cache statically, just
 * because.
 *
 * @param string $solr_url
 *   The Solr URL from which to try to grab the solrconfig.xml.
 *
 * @return SimpleXMLElement
 *   A SimpleXMLElement representing the contents of the solrconfig.xml, or
 *   NULL if it could not be retrieved.
 */
function islandora_solr_get_solrconfig_xml($solr_url) {
  $xml = &drupal_static(__FUNCTION__, []);
  if (!array_key_exists($solr_url, $xml) || $xml[$solr_url] === NULL) {
    $url = $solr_url . '/admin/file/?file=solrconfig.xml';
    // XXX: The Solr URL should be made to contain the URI scheme in the actual
    // configuration.
    if (strpos($solr_url, 'http') !== 0) {
      $url = "http://$url";
    }
    $client = \Drupal::httpClient();
    $req = $client->request('GET', $url);
    if ($req->getStatusCode() / 100 === 2) {
      $xml[$solr_url] = simplexml_load_string($req->getBody()->getContents());
      islandora_solr_resolve_xincludes($xml[$solr_url], (strpos($solr_url, 'http') === 0 ? '' : 'http://') . $solr_url);
    }
    else {
      $xml[$solr_url] = NULL;
    }
  }
  return $xml[$solr_url];
}

/**
 * Resolve XIncludes which include files local to the Solr config directory.
 *
 * Relies upon some internal libxml behaviour... When one imports a
 * SimpleXMLElement into DOM and acts upon it, the original SimpleXMLElement is
 * also changed.
 *
 * @param SimpleXMLElement $doc_element
 *   An SimpleXMLElement under which to resolve XInclude statements. NOTE: This
 *   object will be modified in place.
 * @param string $solr_url
 *   A string representing the path to Solr.
 */
function islandora_solr_resolve_xincludes(SimpleXMLElement $doc_element, $solr_url) {
  $dom_element = dom_import_simplexml($doc_element);
  $doc = $dom_element->ownerDocument;
  $xpath = new DOMXPath($doc);
  $xpath->registerNamespace('xi', 'http://www.w3.org/2001/XInclude');
  $xincludes = $xpath->query('//xi:*[@href and not(contains(@href, "/"))]');
  foreach ($xincludes as $element) {
    $element->setAttribute('href', "$solr_url/admin/file?file=" . $element->getAttribute('href'));
  }

  // XXX: Suppress errors, as trying to access a file which doesn't exist
  // will throw an HTTP 400 instead of a 404... Solr is silly.
  @$doc->xinclude();
}
