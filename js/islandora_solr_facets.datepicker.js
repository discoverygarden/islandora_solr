/**
 * @file
 * Javascript file for datepicker facets.
 */

(function ($, Drupal) {

  // Datepicker.
  Drupal.behaviors.islandoraSolrDatepicker = {
    attach: function (context, settings) {
      $.each(settings.islandora_solr.islandoraSolrDatepickerRange, function () {
        var formKey = this.formKey;
        var yearRangeVal = this.datepickerRange;
        // Set datepicker.
        $(".islandora-solr-datepicker-" + formKey).datepicker({
          changeMonth: true,
          changeYear: true,
          dateFormat: "yy/mm/dd",
          yearRange: yearRangeVal
        });
      });
    }
  }

})(jQuery, Drupal);
