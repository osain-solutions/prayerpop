/**
 * PrayerPop style settings helpers.
 * Keeps admin controls in sync with displayed values.
 */
jQuery(document).ready(function($) {
    'use strict';

    function updateColorValue($input) {
        $input.siblings('.color-value').text($input.val());
    }

    function updateSliderValue($slider) {
        var value = $slider.val();
        var sliderId = $slider.attr('id');

        if (sliderId === 'bubble_size_range') {
            $('#bubble_size_value_display').text(value + '%');
            return;
        }

        if (sliderId === 'bubble_icon_size_range') {
            $('#bubble_icon_size_value_display').text(value + '%');
            return;
        }

        $slider.closest('.prayer-pop-size-control').find('.size-display span').text(value + '%');
    }

    function updateToggleStatus($checkbox) {
        var $wrapper = $checkbox.closest('.prayer-pop-toggle-wrapper');
        var $status = $wrapper.find('.toggle-status');
        $status.text($checkbox.is(':checked') ? 'On' : 'Off');
    }

    function updateBubbleBorderRadiusVisibility() {
        var mode = $('#bubble_design_mode').val() || 'adaptive';
        var $field = $('#bubble_border_radius').closest('.layout-field');
        if (!$field.length) {
            return;
        }

        if (mode === 'fixed_circle') {
            $field.hide();
        } else {
            $field.show();
        }
    }

    $('.prayer-pop-color-input').each(function() {
        updateColorValue($(this));
    });

    $('.prayer-pop-range-slider').each(function() {
        updateSliderValue($(this));
    });

    $('.prayer-pop-toggle-switch input').each(function() {
        updateToggleStatus($(this));
    });

    updateBubbleBorderRadiusVisibility();

    $(document).on('input change', '.prayer-pop-color-input', function() {
        updateColorValue($(this));
    });

    $(document).on('input change', '.prayer-pop-range-slider', function() {
        updateSliderValue($(this));
    });

    $(document).on('change', '.prayer-pop-toggle-switch input', function() {
        updateToggleStatus($(this));
    });

    $(document).on('change', '#bubble_design_mode', function() {
        updateBubbleBorderRadiusVisibility();
    });

});
