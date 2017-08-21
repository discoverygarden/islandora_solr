<?php

/**
 * @file
 * Contains \Drupal\islandora_solr\Form\IslandoraSolrAdminIndexSettings.
 */

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class IslandoraSolrAdminIndexSettings extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_admin_index_settings';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // Add admin form CSS.
    $form['#attached'] = [
      'css' => [
        drupal_get_path('module', 'islandora_solr') . '/css/islandora_solr.admin.css'
        ]
      ];

    $solr_url = $form_state->getValue(['islandora_solr_url']) ?
      $form_state->getValue(['islandora_solr_url']) :
      \Drupal::config('islandora_solr.settings')->get('islandora_solr_url');

    // Solr connect triggering handler is dismax or not set on page load.
    if ((!$form_state->getTriggeringElement() && (($form_state->getTriggeringElement() == 'islandora_solr_url') || ($form_state->getTriggeringElement() == 'islandora_solr_request_handler'))) || $form_state->getTriggeringElement()) {

      // Check for the PHP Solr lib class.
      if (!class_exists('Apache_Solr_Service')) {
        $message = t('This module requires the <a href="!url">Apache Solr PHP Client</a>. Please install the client in the root directory of this module before continuing.', [
          '!url' => 'http://code.google.com/p/solr-php-client'
          ]);
        drupal_set_message(\Drupal\Component\Utility\Html::escape($message));
        return;
      }

      // Get request handler.
      $handler = $form_state->getValue(['islandora_solr_request_handler']) ? $form_state->getValue(['islandora_solr_request_handler']) : \Drupal::config('islandora_solr.settings')->get('islandora_solr_request_handler');

      if (strpos($solr_url, 'https://') !== FALSE && strpos($solr_url, 'https://') == 0) {
        $confirmation_message = format_string('<img src="@image_url"/>!message', [
          '@image_url' => file_create_url('misc/watchdog-error.png'),
          '!message' => t('Islandora does not support SSL connections to Solr.'),
        ]);
        $solr_avail = FALSE;
      }
      else {
        // Check if Solr is available.
        $solr_avail = islandora_solr_ping($solr_url);

        $dismax_allowed = FALSE;
        // If solr is available, get the request handlers.
        if ($solr_avail) {
          // Find request handlers (~500ms).
          $handlers = _islandora_solr_get_handlers($solr_url);
        }
        // Get confirmation message.
        if ($solr_avail) {
          // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $confirmation_message = format_string('<img src="@image_url"/>!message', array(
//           '@image_url' => file_create_url('misc/watchdog-ok.png'),
//           '!message' => t('Successfully connected to Solr server at !link. <sub>(!ms ms)</sub>', array(
//             '!link' => l($solr_url, islandora_solr_check_http($solr_url), array(
//               'attributes' => array(
//                 'target' => '_blank',
//               ),
//             )),
//             '!ms' => number_format($solr_avail, 2),
//           )),
//         ));

        }
        else {
          // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $confirmation_message = format_string('<img src="@image_url"/>!message', array(
//           '@image_url' => file_create_url('misc/watchdog-error.png'),
//           '!message' => t('Unable to connect to Solr server at !link.', array(
//             '!link' => l($solr_url, islandora_solr_check_http($solr_url), array(
//               'attributes' => array(
//                 'target' => '_blank',
//               ),
//             )),
//           )),
//         ));

        }
      }
    }
    // AJAX wrapper for URL checking.
    $form['solr_ajax_wrapper'] = [
      '#prefix' => '<div id="solr-url">',
      '#suffix' => '</div>',
      '#type' => 'fieldset',
    ];
    // Solr URL.
    $form['solr_ajax_wrapper']['islandora_solr_url'] = [
      '#type' => 'textfield',
      '#title' => t('Solr URL'),
      '#size' => 80,
      '#weight' => -1,
      '#description' => t('The URL of the Solr installation. Defaults to localhost:8080/solr.'),
      '#default_value' => $solr_url,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '_islandora_solr_update_solr_url',
        'wrapper' => 'solr-url',
        'effect' => 'fade',
        'event' => 'blur',
        'progress' => [
          'type' => 'throbber'
          ],
      ],
    ];

    // Hidden submit button.
    $form['solr_ajax_wrapper']['refresh_page'] = [
      '#type' => 'submit',
      '#value' => t('Test connection'),
      '#attributes' => [
        'class' => [
          'refresh-button'
          ]
        ],
      '#submit' => ['_islandora_solr_admin_refresh'],
    ];
    // Confirmation message.
    $form['solr_ajax_wrapper']['infobox'] = [
      '#type' => 'item',
      '#markup' => isset($confirmation_message) ? $confirmation_message : $form_state->get(['complete form', 'solr_ajax_wrapper', 'infobox', '#markup']),
    ];

    // Don't show form item if no request handlers are found.
    if (!empty($handlers)) {
      $form['solr_ajax_wrapper']['islandora_solr_request_handler'] = [
        '#type' => 'select',
        '#title' => t('Request handler'),
        '#options' => $handlers,
        '#description' => t('Request handlers, as defined by <a href="!url">solrconfig.xml</a>.', [
          '!url' => 'http://wiki.apache.org/solr/SolrConfigXml'
          ]),
        '#default_value' => $handler,
        '#ajax' => [
          'callback' => '_islandora_solr_update_solr_url',
          'wrapper' => 'solr-url',
          'effect' => 'fade',
          'event' => 'change',
          'progress' => [
            'type' => 'throbber'
            ],
        ],
      ];
    }
    $form['solr_ajax_wrapper']['islandora_solr_available'] = [
      '#type' => 'hidden',
      '#value' => $solr_avail ? 1 : 0,
    ];

    // Solr force delete from index during object purge.
    $form['solr_ajax_wrapper']['islandora_solr_force_update_index_after_object_purge'] = [
      '#type' => 'checkbox',
      '#title' => t('Force update of Solr index after an object is deleted'),
      '#weight' => 5,
      '#description' => t('If checked, deleting objects will also force their removal from the Solr index. <br/><strong>Note:</strong> When active, UI consistency will be increased on any pages using Solr queries for display. This setting is not appropriate for every installation (e.g., on sites with a large volume of Solr commits that hit execution limits, or where the Solr index is not directly writable from Drupal).'),
      '#default_value' => \Drupal::config('islandora_solr.settings')->get('islandora_solr_force_update_index_after_object_purge'),
    ];

    // Actions.
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save Solr configuration'),
      '#weight' => 0,
      '#submit' => [
        '_islandora_solr_admin_index_settings_submit'
        ],
      '#validate' => ['_islandora_solr_admin_index_settings_validate'],
    ];
    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => t('Reset to defaults'),
      '#weight' => 1,
      '#submit' => [
        '_islandora_solr_admin_index_settings_submit'
        ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    module_load_include('inc', 'islandora_solr', 'includes/admin.inc');
    _islandora_solr_admin_index_settings_submit($form, $form_state);
    parent::submitForm($form, $form_state);
  }

}
?>
