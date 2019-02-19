/**
 * @file
 * Javascript file for datepicker facets.
 */

(function ($, Drupal) {

  // Datepicker.
  Drupal.behaviors.islandoraSolrDatepicker = {
    attach: function (context, settings) {
      if (!settings.islandora_solr.islandoraSolrDatepickerRange) {
        return;
      }
      var datepickerRange = settings.islandora_solr.islandoraSolrDatepickerRange;
      $.each(datepickerRange, function () {
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
