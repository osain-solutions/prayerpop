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

(function($) {
    'use strict';

    var config = window.prayerPopStyleSettings || {};
    var strings = config.strings || {};
    var datasetPromise = null;
	var loadedIconNodes = {};

    function loadIconNodes() {
        if (!datasetPromise) {
            datasetPromise = window.fetch(config.datasetUrl, { cache: 'force-cache' }).then(function(response) {
                if (!response.ok) {
                    throw new Error('Dataset load failed');
                }
                return response.json();
            });
        }
        return datasetPromise;
    }

    function humanize(value) {
        return String(value || '').replace(/-/g, ' ').replace(/\b\w/g, function(character) {
            return character.toUpperCase();
        });
    }

    function createTablerSvg(nodes) {
        if (!Array.isArray(nodes)) {
            return null;
        }

        var namespace = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(namespace, 'svg');
        var rootAttributes = {
            'class': 'prayer-pop-tabler-icon',
            'width': '24',
            'height': '24',
            'viewBox': '0 0 24 24',
            'fill': 'none',
            'stroke': 'currentColor',
            'stroke-width': '2',
            'stroke-linecap': 'round',
            'stroke-linejoin': 'round',
            'aria-hidden': 'true',
            'focusable': 'false'
        };
        Object.keys(rootAttributes).forEach(function(attribute) {
            svg.setAttribute(attribute, rootAttributes[attribute]);
        });

        var allowedAttributes = ['d', 'fill', 'opacity', 'stroke', 'transform', 'stroke-width'];
        nodes.forEach(function(definition) {
            if (!Array.isArray(definition) || definition.length !== 2 || ['path', 'g'].indexOf(definition[0]) === -1) {
                return;
            }
            var node = document.createElementNS(namespace, definition[0]);
            var attributes = definition[1] || {};
            Object.keys(attributes).forEach(function(attribute) {
                if (allowedAttributes.indexOf(attribute) !== -1) {
                    node.setAttribute(attribute, String(attributes[attribute]));
                }
            });
            svg.appendChild(node);
        });
        return svg;
    }

    function previewBackground() {
        var bubble = $('#bubble_bg_color').val();
        var global = $('#global_bg_color').val();
        return /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test(bubble || '') ? bubble :
            (/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test(global || '') ? global : '#2755AA');
    }

    function previewIconColor() {
        var color = $('#bubble_icon_color').val();
        return /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test(color || '') ? color : '#ffffff';
    }

    function updatePreviewColors($preview) {
        $preview.css({
            'background-color': previewBackground(),
            'color': previewIconColor()
        });
    }

    function resultMessage(total, limit) {
        if (total > limit) {
            return (strings.showingFirst || 'Showing first') + ' ' + limit + ' ' +
                (strings.ofLabel || 'of') + ' ' + total + ' ' +
                (strings.resultsNarrow || 'results. Keep typing to narrow down.');
        }
        return total + ' ' + (strings.iconCountLabel || 'icon(s)');
    }

	function applyCombinedIconSelection($select) {
		var value = String($select.val() || '');
		var separator = value.indexOf(':');
		if (separator < 1) {
			return;
		}

		var source = value.substring(0, separator);
		var key = value.substring(separator + 1);
		var $preview = $('#dashicon_preview');
		var selectedLabel = $select.find('option:selected').text().split(' (')[0];

		if (source === 'tabler') {
			$('#bubble_icon_type').val('tabler').trigger('change');
			$('#bubble_tabler_icon').val(key).trigger('change');
			$('#dashicon_name').text('Tabler • ' + humanize(key));
			$('#dashicon_class').text('tabler:' + key);
			if (loadedIconNodes[key]) {
				$preview.empty().append(createTablerSvg(loadedIconNodes[key]));
			}
		} else {
			$('#bubble_icon_type').val('dashicon').trigger('change');
			$('#bubble_dashicon').val(key).trigger('change');
			$('#dashicon_name').text(selectedLabel);
			$('#dashicon_class').text(key === 'prayerpop' ? 'dashicon:prayerpop' : 'dashicons-' + key);
			$preview.empty();
			if (key === 'prayerpop') {
				$('<img>').attr({ src: config.prayerPopIconUrl || '', alt: 'PrayerPop icon' }).addClass('prayer-pop-brand-icon').appendTo($preview);
			} else {
				$('<span>').addClass('dashicons dashicons-' + key.replace(/[^a-z0-9-]/gi, '')).appendTo($preview);
			}
		}

		updatePreviewColors($preview);
	}

	$(document).on('input change click', '#bubble_icon_library_select', function() {
		applyCombinedIconSelection($(this));
	});

    function initializeCombinedSelector(iconNodes) {
        var $select = $('#bubble_icon_library_select');
        if (!$select.length) {
            return;
        }

        var $search = $('#dashicon_search');
        var $count = $('#icon_library_results_count');
        var $preview = $('#dashicon_preview');
        var index = [];
        var selectedValue = String($select.val() || '');
        $select.find('option[data-source]').each(function() {
            var $option = $(this);
            var source = String($option.data('source') || '');
            var key = String($option.data('key') || '');
            if (source && key) {
                index.push({
                    value: source + ':' + key,
                    source: source,
                    key: key,
                    label: $option.text(),
                    search: String($option.data('search') || $option.text()).toLowerCase()
                });
            }
        });

        Object.keys(iconNodes).sort().forEach(function(name) {
            index.push({
                value: 'tabler:' + name,
                source: 'tabler',
                key: name,
                label: 'Tabler • ' + humanize(name) + ' (' + name + ')',
                search: (name + ' ' + humanize(name) + ' tabler svg').toLowerCase()
            });
        });

        function currentValue() {
            var value = String($select.val() || selectedValue || '');
            if (value) {
                return value;
            }
            return $('#bubble_icon_type').val() === 'tabler' ?
                'tabler:' + ($('#bubble_tabler_icon').val() || 'pray') :
                'dashicon:' + ($('#bubble_dashicon').val() || 'prayerpop');
        }

        function render(term) {
            var query = String(term || '').toLowerCase().trim();
            var matches = index.filter(function(icon) {
                return !query || icon.search.indexOf(query) !== -1;
            });
            var selected = currentValue();
            var shown = matches.slice(0, 400);
            if (selected && !shown.some(function(icon) { return icon.value === selected; })) {
                var selectedIcon = matches.find(function(icon) { return icon.value === selected; });
                if (selectedIcon) {
                    shown.unshift(selectedIcon);
                }
            }

            $select.empty();
            shown.slice(0, 400).forEach(function(icon) {
                $('<option>')
                    .val(icon.value)
                    .text(icon.label)
                    .attr('data-source', icon.source)
                    .attr('data-key', icon.key)
                    .prop('selected', icon.value === selected)
                    .appendTo($select);
            });
            if (!shown.length) {
                $('<option>').val('').text(strings.noIconsFound || 'No icons found').prop('disabled', true).appendTo($select);
            }
            $count.text(resultMessage(matches.length, 400));
        }

        function updateSelection() {
            var value = String($select.val() || '');
            var separator = value.indexOf(':');
            if (separator < 1) {
                return;
            }
            var source = value.substring(0, separator);
            var key = value.substring(separator + 1);
            selectedValue = value;
            $preview.empty();

            if (source === 'tabler' && iconNodes[key]) {
                $('#bubble_icon_type').val('tabler');
                $('#bubble_tabler_icon').val(key);
                $('#dashicon_name').text('Tabler • ' + humanize(key));
                $('#dashicon_class').text('tabler:' + key);
                $preview.append(createTablerSvg(iconNodes[key]));
            } else {
                $('#bubble_icon_type').val('dashicon');
                $('#bubble_dashicon').val(key);
                $('#dashicon_name').text($select.find('option:selected').text().split(' (')[0]);
                if (key === 'prayerpop') {
                    $('#dashicon_class').text('dashicon:prayerpop');
                    $('<span>').addClass('prayer-pop-brand-icon-mask').attr('aria-hidden', 'true').appendTo($preview);
                } else {
                    $('#dashicon_class').text('dashicons-' + key);
                    $('<span>').addClass('dashicons dashicons-' + key.replace(/[^a-z0-9-]/gi, '')).appendTo($preview);
                }
            }
            updatePreviewColors($preview);
            $select.trigger('prayerpop:icon-selected');
        }

        $preview.css('--prayer-pop-brand-icon-url', 'url("' + String(config.prayerPopIconUrl || '').replace(/["\\]/g, '\\$&') + '")');
        $search.on('input', function() { render($(this).val()); });
        $('#dashicon_clear_search').on('click', function() {
            $search.val('');
            render('');
            $search.trigger('focus');
        });
        $select.on('change', updateSelection);
        $(document).on('input change', '#global_bg_color, #bubble_bg_color, #bubble_icon_color', function() {
            updatePreviewColors($preview);
        });
        render('');
        updateSelection();
    }

    function initializeTablerSelector(iconNodes) {
        var $select = $('#bubble_tabler_icon_select');
        if (!$select.length) {
            return;
        }

        var $hidden = $('#bubble_tabler_icon');
        var $search = $('#tabler_icon_search');
        var $preview = $('#tabler_icon_preview');
        var index = Object.keys(iconNodes).sort().map(function(name) {
            return { name: name, label: humanize(name), search: (name + ' ' + humanize(name)).toLowerCase() };
        });

        function updateSelection(name) {
            if (!name || !iconNodes[name]) {
                return;
            }
            $hidden.val(name);
            $('#tabler_icon_name').text(humanize(name));
            $('#tabler_icon_key').text(name);
            $preview.empty().append(createTablerSvg(iconNodes[name]));
            updatePreviewColors($preview);
        }

        function render(term) {
            var query = String(term || '').toLowerCase().trim();
            var matches = index.filter(function(icon) { return !query || icon.search.indexOf(query) !== -1; });
            var selected = String($hidden.val() || config.initialIcon || 'pray');
            $select.empty();
            matches.slice(0, 300).forEach(function(icon) {
                $('<option>').val(icon.name).text(icon.label + ' (' + icon.name + ')').prop('selected', icon.name === selected).appendTo($select);
            });
            if (!matches.length) {
                $('<option>').val('').text(strings.noIconsFound || 'No icons found').prop('disabled', true).appendTo($select);
            }
            $('#tabler_icon_results_count').text(resultMessage(matches.length, 300));
        }

        $search.on('input', function() { render($(this).val()); });
        $('#tabler_icon_clear_search').on('click', function() {
            $search.val('');
            render('');
            $search.trigger('focus');
        });
        $select.on('change', function() { updateSelection($(this).val()); });
        $(document).on('input change', '#global_bg_color, #bubble_bg_color, #bubble_icon_color', function() {
            updatePreviewColors($preview);
        });
        render('');
        updateSelection(String($hidden.val() || config.initialIcon || 'pray'));
    }

    $(function() {
        if (!$('#bubble_icon_library_select, #bubble_tabler_icon_select').length) {
            return;
        }
        loadIconNodes().then(function(iconNodes) {
			loadedIconNodes = iconNodes || {};
            initializeCombinedSelector(iconNodes || {});
            initializeTablerSelector(iconNodes || {});
        }).catch(function() {
			initializeCombinedSelector({});
            $('#icon_library_results_count').text(strings.tablerLoadFailed || 'Tabler dataset could not be loaded.');
            $('#tabler_icon_results_count').text(strings.tablerLoadFailedResave || 'Could not load Tabler icon data. Re-save or refresh the page.');
        });
    });
})(jQuery);
