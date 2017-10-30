/**
 * @file
 * Javascript file for toggling truncation.
 */

(function ($, Drupal) {
  "use strict";

  Drupal.behaviors.islandoraSolrTruncationToggle = {
    attach: function (context, settings) {
      $('.toggle-wrapper', context).once('truncation-toggle').each(function () {
        var $this = $(this);
        $this.find('> span').hide().first().show();
      });
      $('.toggle-wrapper .toggler', context).once('truncation-toggle').each(function () {
        var $this = $(this);
        $this.click(function (event) {
          event.preventDefault();
          $this.closest('.toggle-wrapper').find('> span').toggle();
        });
      });
    }
  };
})(jQuery, Drupal);
