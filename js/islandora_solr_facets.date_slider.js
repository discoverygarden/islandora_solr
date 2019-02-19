/**
 * @file
 * Javascript for date slider elements.
 */

(function ($, Drupal) {

  // Range slider.
  Drupal.behaviors.islandoraSolrRangeSlider = {
    attach: function (context, settings) {
      // Loop over each range slider facet.
      $.each(settings.islandora_solr.islandoraSolrRangeSlider, function () {
        // Set variables.
        var sliderData = this.data;
        var form_key = this.form_key;
        var sliderId = '#date-range-slider-' + form_key;
        var amountId = '#slider-amount-' + form_key;
        var canvasId = '#date-range-slider-canvas-' + form_key;
        var rangeSliderColor = this.slider_color;
        if (!rangeSliderColor) {
          rangeSliderColor = '#edc240';
        }
        var sliderMax = sliderData.length - 1;
        var sliderMin = 0;
        var sliderStep = 1;

        // Set jquery ui slider.
        $(sliderId).slider({
          range: true,
          handles: [{start:sliderMin, min:sliderMin, max:sliderMax, id:'range-slider-handle-min-' + form_key}, {start:sliderMax, min:sliderMin, max:sliderMax, id:'range-slider-handle-max-' + form_key}],
          values: [sliderMin, sliderMax],
          min: sliderMin,
          max: sliderMax,
          step: sliderStep,
          slide: function (event, ui) {
            slider_update(ui.values[0], ui.values[1]);
          },
          slide: function (event, ui) {
            slider_update(ui.values[0], ui.values[1]);
          }
        });

        // Function to update the slider values and position.
        function slider_update(fromVal, toVal) {
          // Get dates.
          var fromDate = sliderData[fromVal].date;
          var toDate = sliderData[toVal].date;

          // Assign to hidden field.
          $('.range-slider-hidden-from-' + form_key).val(fromDate);
          $('.range-slider-hidden-to-' + form_key).val(toDate);

          // Get formatted dates.
          var formatFromDate = sliderData[fromVal].bucket;
          var formatToDate = sliderData[toVal].bucket;

          // Update slider values.
          $(sliderId).slider('values', 0, fromVal);
          $(sliderId).slider('values', 1, toVal);

          // Assign to popup.
          $(sliderId + ' .slider-popup-from').html(formatFromDate);
          $(sliderId + ' .slider-popup-to').html(formatToDate);

          // Update plots.
          plot.unhighlight();
          for (i = fromVal; i < toVal; i++) {
            plot.highlight(0, i);
          }
        }

        // Set canvas width to auto for responsiveness.
        $(canvasId).width('auto').height('120px');

        // Set color for the slider.
        $(sliderId + ' .ui-slider-range').css({'background': rangeSliderColor});

        // Add classes to slider handles.
        $(sliderId + ' > a:eq(0)').addClass('handle-min').prepend('<div class="slider-popup-from-wrapper slider-popup"><span class="slider-popup-from">' + sliderData[0].bucket + '</span></div>').hover(function () {
          $('#range-slider-tooltip').remove();
          $(this).find('.slider-popup-from-wrapper').stop(false, true).fadeIn(0);
        }, function () {
          $(this).find('.slider-popup-from-wrapper').stop(false, true).fadeOut('slow');
        });

        $(sliderId + ' > a:eq(1)').addClass('handle-max').prepend('<div class="slider-popup-to-wrapper slider-popup"><span class="slider-popup-to">' + sliderData[sliderData.length - 1].bucket + '</span></div>').hover(function () {
          $('#range-slider-tooltip').remove();
          $(this).find('.slider-popup-to-wrapper').stop(false, true).fadeIn(0);
        }, function () {
          $(this).find('.slider-popup-to-wrapper').stop(false, true).fadeOut('slow');
        });

        // Add aria-label for WCAG 2.0 compliance.
        $(sliderId + ' > a:eq(0)').attr('aria-label', "From");
        $(sliderId + ' > a:eq(1)').attr('aria-label', "To");

        // Prepare flot data.
        var d1 = [];
        for (var i = 0; i <= sliderMax - 1; i += 1) {
          d1.push([i, this.data[i].count]);
        }

        // Render Flot graph.
        var plot = $.plot($(canvasId), [d1], {
          colors: [rangeSliderColor],
          xaxis: {  ticks: [], min: 0, autoscaleMargin: 0},
          yaxis: {  ticks: [], min: 0, autoscaleMargin: 0},
          series: {
            stack: false,
            lines: {
              show: false
            },
            bars: {
              show: true,
              lineWidth: 1, // In pixels.
              barWidth: 0.8, // In units of the x axis.
              fill: true,
              fillColor: null,
              align: "left", // Or "center".
              horizontal: false
            }
          },
          grid: {
            show: true,
            labelMargin: null, // In pixels.
            axisMargin: null, // In pixels.
            borderWidth: null, // In pixels.
            markingsLineWidth: null,
            // Interactive stuff.
            clickable: true,
            hoverable: true,
            autoHighlight: false, // Highlight in case mouse is near.
            mouseActiveRadius: 0 // How far the mouse can be away to activate an item.
          }
        });

        // Add plotclick event to update the sliders.
        $(canvasId).bind("plotclick", function (event, pos, item) {
          if (item !== null) {
            // Get variable.
            var dataIndexValue = item.dataIndex;
            // Update the slider and form values.
            slider_update(dataIndexValue, dataIndexValue + 1);
          }
        });

        // Show tooltip.
        function show_tooltip(x, y, contents) {
          // Hide or remove all other popups.
          $('#range-slider-tooltip').remove();
          $('.slider-popup').hide();
          $('<div id="range-slider-tooltip"></div>').css({
              top: y - 50,
              left: x - 125
          }).html('<span>' + contents + '</span>').appendTo("body").fadeIn(0);
        }

        var previousPoint = null;
        // Bind plothover.
        $(canvasId).bind("plothover", function (event, pos, item) {
          if (item) {
              previousPoint = item.dataIndex;

              // Fadeout and remove.
              $('#range-slider-tooltip').fadeOut('slow', function () {
                $(this).remove();
              });

              // Update mouse position.
              var x = pos.pageX,
                  y = pos.pageY;

              // Get variable.
              var dataIndexValue = item.dataIndex;
              var dataIndexValueNext = dataIndexValue + 1;
              var tooltipContent = sliderData[dataIndexValue].bucket + ' - ' + sliderData[dataIndexValueNext].bucket + ' (<em>' + sliderData[dataIndexValue].count + '</em>)';

              // Call show tooltip function.
              show_tooltip(pos.pageX, pos.pageY, tooltipContent);
          }
          else {
            // Fadeout and remove.
            $('#range-slider-tooltip').fadeOut('slow', function () {
              $(this).remove();
            });
            previousPoint = null;
          }
        });
      });
    }
  }

})(jQuery, Drupal);
