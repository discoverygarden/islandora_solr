/**
 * @file
 * Javascript file for islandora solr search facets.
 */

(function ($, Drupal) {

  // Adds facet toggle functionality.
  Drupal.behaviors.islandoraSolrToggle = {
    attach: function (context, settings) {
      // Show more.
      if (!$(".soft-limit").hasClass('processed')) {
        $(".soft-limit").click(function (e) {
          // Toggle class .hidden.
          $(this).prev(".islandora-solr-facet").toggleClass('hidden');
          if ($(this).text() == Drupal.t('Show more')) {
            $(this).text(Drupal.t('Show less'));
          }
          else {
            $(this).text(Drupal.t('Show more'));
          }
          e.preventDefault();
        });
        $(".soft-limit").addClass('processed');
      }
    }
  }

  // Show/hide date filter.
  Drupal.behaviors.islandoraSolrDateFilter = {
    attach: function (context, settings) {
      // Set variables.
      var stringHide = Drupal.t('Hide');
      var stringShow = Drupal.t('Show');

      if (!$('.toggle-date-range-filter').hasClass('processed')) {

        // Hide all regions that should be collapsed.
        $('.date-range-collapsed').parent('.date-filter-toggle-text').next('.date-range-filter-wrapper').css({'display': 'none'});

        $('.toggle-date-range-filter').click(function () {
          // Toggle strings.
          if ($(this).html() == stringHide) {
            $(this).html(stringShow);
          }
          else {
            $(this).html(stringHide);
          }

          // Toggle wrapper.
          $(this).parent('.date-filter-toggle-text').next('.date-range-filter-wrapper').slideToggle('fast');

          return false;
        });

        $('.toggle-date-range-filter').addClass('processed');
      }
    }
  }

})(jQuery, Drupal);
