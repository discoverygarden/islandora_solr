<?php

namespace Drupal\islandora_solr_config\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

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

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get variables.
    $rss_item = $this->config('islandora_solr_config.settings')->get('islandora_solr_config_rss_item');
    $rss_channel = $this->config('islandora_solr_config.settings')->get('islandora_solr_config_rss_channel');

    $form['#tree'] = TRUE;

    $form['rss_item'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Item settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#description' => $this->t('If the Solr Search Results Display fields are
        limited, only those fields which are configured for display can be used
        here; if an un-configured field is used it will be ignored. To take full
        control over the RSS item output, you can also override the following
        method: IslandoraSolrResultsRSS::rssItem()'),
    ];
    $form['rss_item']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('Solr field to populate the item title.'),
      '#autocomplete_route_name' => 'islandora_solr.autocomplete_luke',
      '#default_value' => $rss_item['title'],
      '#required' => TRUE,
    ];
    $form['rss_item']['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Solr field to populate the item synopsis.'),
      '#autocomplete_route_name' => 'islandora_solr.autocomplete_luke',
      '#default_value' => $rss_item['description'],
    ];
    $form['rss_item']['author'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Author'),
      '#description' => $this->t('Solr field to populate the item author.'),
      '#autocomplete_route_name' => 'islandora_solr.autocomplete_luke',
      '#default_value' => $rss_item['author'],
    ];
    $form['rss_item']['category'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Category'),
      '#description' => $this->t('Solr field to populate the item category.'),
      '#autocomplete_route_name' => 'islandora_solr.autocomplete_luke',
      '#default_value' => $rss_item['category'],
    ];
    $form['rss_item']['pubDate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publication date'),
      '#description' => $this->t('Solr field to populate the item publication date (pubDate).'),
      '#autocomplete_route_name' => 'islandora_solr.autocomplete_luke',
      '#default_value' => $rss_item['pubDate'],
    ];
    $form['rss_item']['enclosure_dsid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enclosure (datastream ID)'),
      '#description' => $this->t('Fill out a datastream ID to be added as a media enclosure. Defaults to thumbnail (TN).'),
      '#default_value' => $rss_item['enclosure_dsid'],
    ];
    $form['rss_channel'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Channel settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#description' => $this->t('To take full control over the RSS channel
        output, you can also override the following method:
        IslandoraSolrResultsRSS::rssChannel()'),
    ];
    $form['rss_channel']['copyright'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Copyright'),
      '#description' => $this->t('Copyright notice for content in the channel.'),
      '#default_value' => $rss_channel['copyright'],
    ];
    $form['rss_channel']['managingEditor'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Managing editor'),
      '#description' => $this->t('Email address for person responsible for editorial content.'),
      '#default_value' => $rss_channel['managingEditor'] ? $rss_channel['managingEditor'] : '',
    ];
    $form['rss_channel']['webMaster'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Webmaster'),
      '#description' => $this->t('Email address for person responsible for technical issues relating to channel.'),
      '#default_value' => $rss_channel['webMaster'] ? $rss_channel['webMaster'] : '',
    ];
    $form['buttons']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#weight' => 50,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('islandora_solr_config.settings');
    $rss_item = $form_state->getValue('rss_item');
    $rss_channel = $form_state->getValue('rss_channel');

    // Set variable.
    $config->set('islandora_solr_config_rss_item', $rss_item);
    $config->set('islandora_solr_config_rss_channel', $rss_channel);
    $config->save();
  }

}
