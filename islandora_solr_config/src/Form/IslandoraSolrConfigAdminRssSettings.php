<?php

/**
 * @file
 * Contains \Drupal\islandora_solr_config\Form\IslandoraSolrConfigAdminRssSettings.
 */

namespace Drupal\islandora_solr_config\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Admin settings for the RSS exposed by Solr.
 */
class IslandoraSolrConfigAdminRssSettings extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_config_admin_rss_settings';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // Get variables.
  // @FIXME
// Could not extract the default value because it is either indeterminate, or
// not scalar. You'll need to provide a default value in
// config/install/islandora_solr_config.settings.yml and config/schema/islandora_solr_config.schema.yml.
    $rss_item = \Drupal::config('islandora_solr_config.settings')->get('islandora_solr_config_rss_item');
    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/islandora_solr_config.settings.yml and config/schema/islandora_solr_config.schema.yml.
    $rss_channel = \Drupal::config('islandora_solr_config.settings')->get('islandora_solr_config_rss_channel');

    $form = ['#tree' => TRUE];

    $form['rss_item'] = [
      '#type' => 'fieldset',
      '#title' => t('Item settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#description' => t('If the Solr Search Results Display fields are limited, only those fields which are configured for display can be used here, if an un-configured field is used it will be ignored. To take full control over the RSS item output you can also override the following method: IslandoraSolrResultsRSS::rssItem()'),
    ];
    $form['rss_item']['title'] = [
      '#type' => 'textfield',
      '#title' => t('Title'),
      '#description' => t('Solr field to populate the item title.'),
      '#autocomplete_path' => 'islandora_solr/autocomplete_luke',
      '#default_value' => $rss_item['title'],
      '#required' => TRUE,
    ];
    $form['rss_item']['description'] = [
      '#type' => 'textfield',
      '#title' => t('Description'),
      '#description' => t('Solr field to populate the item synopsis.'),
      '#autocomplete_path' => 'islandora_solr/autocomplete_luke',
      '#default_value' => $rss_item['description'],
    ];
    $form['rss_item']['author'] = [
      '#type' => 'textfield',
      '#title' => t('Author'),
      '#description' => t('Solr field to populate the item author.'),
      '#autocomplete_path' => 'islandora_solr/autocomplete_luke',
      '#default_value' => $rss_item['author'],
    ];
    $form['rss_item']['category'] = [
      '#type' => 'textfield',
      '#title' => t('Category'),
      '#description' => t('Solr field to populate the item category.'),
      '#autocomplete_path' => 'islandora_solr/autocomplete_luke',
      '#default_value' => $rss_item['category'],
    ];
    $form['rss_item']['pubDate'] = [
      '#type' => 'textfield',
      '#title' => t('Publication date'),
      '#description' => t('Solr field to populate the item publication date (pubDate).'),
      '#autocomplete_path' => 'islandora_solr/autocomplete_luke',
      '#default_value' => $rss_item['pubDate'],
    ];
    $form['rss_item']['enclosure_dsid'] = [
      '#type' => 'textfield',
      '#title' => t('Enclosure (datastream ID)'),
      '#description' => t('Fill out a datastream ID to be added as a media enclosure. Defaults to thumbnail (TN).'),
      '#default_value' => $rss_item['enclosure_dsid'],
    ];
    $form['rss_channel'] = [
      '#type' => 'fieldset',
      '#title' => t('Channel settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#description' => t('To take full control over the RSS channel output you can also override the following method: IslandoraSolrResultsRSS::rssChannel()'),
    ];
    $form['rss_channel']['copyright'] = [
      '#type' => 'textfield',
      '#title' => t('Copyright'),
      '#description' => t('Copyright notice for content in the channel.'),
      '#default_value' => $rss_channel['copyright'],
    ];
    $form['rss_channel']['managingEditor'] = [
      '#type' => 'textfield',
      '#title' => t('Managing editor'),
      '#description' => t('Email address for person responsible for editorial content.'),
      '#default_value' => $rss_channel['managingEditor'] ? $rss_channel['managingEditor'] : '',
    ];
    $form['rss_channel']['webMaster'] = [
      '#type' => 'textfield',
      '#title' => t('Webmaster'),
      '#description' => t('Email address for person responsible for technical issues relating to channel.'),
      '#default_value' => $rss_channel['webMaster'] ? $rss_channel['webMaster'] : '',
    ];
    $form['buttons']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
      '#weight' => 50,
    ];
    $form['buttons']['reset'] = [
      '#type' => 'submit',
      '#value' => t('Reset to defaults'),
      '#weight' => 51,
    ];

    return $form;
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {

    // On save.
    if ($form_state->get(['clicked_button', '#value']) == t('Save')) {

      // Get values.
      $rss_item = $form_state->getValue(['rss_item']);
      $rss_channel = $form_state->getValue(['rss_channel']);

      // Set variable.
      \Drupal::configFactory()->getEditable('islandora_solr_config.settings')->set('islandora_solr_config_rss_item', $rss_item)->save();
      \Drupal::configFactory()->getEditable('islandora_solr_config.settings')->set('islandora_solr_config_rss_channel', $rss_channel)->save();
    }

    // On reset.
    if ($form_state->get(['clicked_button', '#value']) == t('Reset to defaults')) {
      \Drupal::config('islandora_solr_config.settings')->clear('islandora_solr_config_rss_item')->save();
      \Drupal::config('islandora_solr_config.settings')->clear('islandora_solr_config_rss_channel')->save();
    }
  }

}
