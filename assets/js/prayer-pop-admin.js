/**
 * PrayerPop Admin JavaScript
 */
jQuery(document).ready(function($) {
    'use strict';

    // Variables for sticky save bar
    var $stickyBar = $('#prayer-pop-sticky-save-bar');
    var $settingsForm = $('#prayer-pop-settings-form');
    var formChanged = false;
    var originalFormData = '';
    var formTrackingReady = false;

    function getCurrentSettingsUrl(activeTab, scrollY) {
        var nextUrl = new URL(window.location.href);
        nextUrl.searchParams.set('page', 'prayer-pop-settings');
        nextUrl.searchParams.set('tab', activeTab || 'popup');

        if (typeof scrollY === 'number' && scrollY > 0) {
            nextUrl.searchParams.set('prayer_pop_scroll', String(Math.max(0, Math.round(scrollY))));
        } else {
            nextUrl.searchParams.delete('prayer_pop_scroll');
        }

        return nextUrl.pathname + nextUrl.search;
    }

    function restoreSettingsScroll() {
        if (!$settingsForm.length) {
            return;
        }

        var params = new URLSearchParams(window.location.search);
        var scrollValue = parseInt(params.get('prayer_pop_scroll') || '0', 10);
        if (!scrollValue || scrollValue < 1) {
            return;
        }

        window.setTimeout(function() {
            window.scrollTo(0, scrollValue);
        }, 80);
    }

    // Function to show the selected tab content
    function showTab(tabId, updateUrl) {
        if (!tabId || $('#' + tabId).length === 0) {
            tabId = 'popup';
        }

        // Hide all tab content
        $('.tab-content').hide();
        
        // Show the selected tab content
        $('#' + tabId).show();
        
        // Update active tab class
        $('.nav-tab').removeClass('nav-tab-active');
        $('.nav-tab[data-tab="' + tabId + '"]').addClass('nav-tab-active');
        
        // Update hidden field
        $('input[name="prayer_pop_active_tab"]').val(tabId);
        
        if (updateUrl !== false) {
            // Update URL without page reload
            var newUrl = new URL(window.location.href);
            newUrl.searchParams.set('tab', tabId);
            window.history.pushState({ path: newUrl.href }, '', newUrl.href);
        }

        // Trigger resize event to fix any layout issues
        $(window).trigger('resize');

		if (tabId === 'documentation') {
			hideStickyBar();
			formChanged = false;
		}

		$('.prayer-pop-settings-save-row').toggleClass('is-hidden', tabId === 'documentation');

    }

    // Initialize form tracking
    function initFormTracking() {
        if ($settingsForm.length) {
            originalFormData = $settingsForm.serialize();
            
            // Monitor form changes
            $settingsForm.on('change input', 'input, select, textarea', function() {
                checkFormChanges();
            });
            
            // Monitor toggle switches specifically
            $settingsForm.on('change', '.prayer-pop-toggle-switch input[type="checkbox"]', function() {
                checkFormChanges();
            });
        }
    }

    function resetFormTrackingBaseline() {
        if (!$settingsForm.length) {
            return;
        }

        originalFormData = $settingsForm.serialize();
        formChanged = false;
        hideStickyBar();
    }

    // Check if form has changes
    function checkFormChanges() {
        if (!$settingsForm.length || !formTrackingReady) {
            return;
        }

		if (($('.nav-tab-active').data('tab') || 'popup') === 'documentation') {
			hideStickyBar();
			formChanged = false;
			return;
		}

        var currentFormData = $settingsForm.serialize();
        var hasChanges = (currentFormData !== originalFormData);
        
        if (hasChanges && !formChanged) {
            showStickyBar();
            formChanged = true;
        } else if (!hasChanges && formChanged) {
            hideStickyBar();
            formChanged = false;
        }
    }

    // Show sticky save bar
    function showStickyBar() {
        $stickyBar.addClass('show');
    }

    // Hide sticky save bar
    function hideStickyBar() {
        $stickyBar.removeClass('show');
    }

    // Handle sticky save button click
    $('#sticky-save-btn').on('click', function(e) {
        e.preventDefault();
        
        // Find the regular submit button and trigger it
        var $submitBtn = $settingsForm.find('input[type="submit"]');
        if ($submitBtn.length) {
            $submitBtn.click();
        }
    });

    // Handle tab clicks
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var tabId = $(this).data('tab');
        showTab(tabId, true);
    });

    // Handle toggle switch changes
    $('.prayer-pop-toggle-switch input[type="checkbox"]').on('change', function() {
        var $status = $(this).siblings('.toggle-status');
        $status.text($(this).is(':checked') ? 'On' : 'Off');
        
        // Handle notification fields enable/disable
        if ($(this).attr('id') === 'enable_notifications_toggle') {
            toggleNotificationFields($(this).is(':checked'));
        }
    });

    // Function to enable/disable notification fields
    function toggleNotificationFields(enabled) {
        var $notificationFields = $('.notification-field');
        
        if (enabled) {
            $notificationFields.prop('disabled', false).removeClass('disabled');
        } else {
            $notificationFields.prop('disabled', true).addClass('disabled');
        }

        toggleNotificationDebugPanel();
    }

    // Show/hide notification debug panel based on toggles
    function toggleNotificationDebugPanel() {
        var notificationsEnabled = $('#enable_notifications_toggle').is(':checked');
        var debugEnabled = $('#prayer_pop_notification_show_debug_info').is(':checked');
        var $debugPanel = $('.prayer-pop-notification-debug-panel');

        if (!$debugPanel.length) {
            return;
        }

        if (notificationsEnabled && debugEnabled) {
            $debugPanel.stop(true, true).slideDown(120);
        } else {
            $debugPanel.stop(true, true).slideUp(120);
        }
    }

    // Initialize notification fields state on page load
    function initNotificationFields() {
        var $enableToggle = $('#enable_notifications_toggle');
        if ($enableToggle.length) {
            toggleNotificationFields($enableToggle.is(':checked'));
        }
        toggleNotificationDebugPanel();
    }

    // Show initial tab from URL or use the task-oriented default.
    var urlParams = new URLSearchParams(window.location.search);
    var initialTab = urlParams.get('tab') || (window.prayerPopAdmin && prayerPopAdmin.activeTab) || $('input[name="prayer_pop_active_tab"]').val() || 'popup';
    showTab(initialTab, false);

    // Handle form submission
    $('#prayer-pop-settings-form').on('submit', function(e) {
        // Store the active tab
        var activeTab = $('.nav-tab-active').data('tab') || 'popup';
        $('input[name="prayer_pop_active_tab"]').val(activeTab);
        $('input[name="_wp_http_referer"]').val(getCurrentSettingsUrl(activeTab, window.pageYOffset || document.documentElement.scrollTop || 0));
        
        // Hide sticky bar when form is submitted
        hideStickyBar();
        formChanged = false;
        
        // Update original form data after a brief delay to account for form processing
        setTimeout(function() {
            originalFormData = $settingsForm.serialize();
        }, 100);
    });

    // Handle dismissible notices
    $(document).on('click', '.notice-dismiss', function() {
        $(this).closest('.notice').fadeOut();
    });

    $(document).on('click', '#prayer-pop-preview-reload', function(e) {
        e.preventDefault();
        var $frame = $('#prayer-pop-frontend-preview-frame');
        if ($frame.length) {
            $frame.attr('src', $frame.attr('src'));
        }
    });

    // Handle browser back/forward buttons
    window.onpopstate = function(event) {
        if (event.state && event.state.path) {
            var urlParams = new URLSearchParams(new URL(event.state.path).search);
            var tab = urlParams.get('tab') || 'popup';
            showTab(tab, false);
        }
    };

    // Welcome modal (shown after activation)
    function closeWelcomeModal() {
        var $welcomeModal = $('#prayer-pop-welcome-modal');
        if (!$welcomeModal.length) {
            return;
        }
        $welcomeModal.removeClass('is-open').attr('aria-hidden', 'true');
        $('body').css('overflow', '');

        var url = new URL(window.location.href);
        if (url.searchParams.has('prayer_pop_welcome')) {
            url.searchParams.delete('prayer_pop_welcome');
            window.history.replaceState({ path: url.href }, '', url.href);
        }
    }

    var $welcomeModal = $('#prayer-pop-welcome-modal');
    if ($welcomeModal.hasClass('is-open')) {
        $('body').css('overflow', 'hidden');
    }

    $(document).on('click', '[data-welcome-close="1"]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeWelcomeModal();
    });

    // Fallback close binding directly on close icon.
    $welcomeModal.on('click', '.prayer-pop-welcome-modal__close', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeWelcomeModal();
    });

    $(document).on('keydown', function(e) {
        var isEscape = (e.key === 'Escape' || e.key === 'Esc' || e.keyCode === 27);
        if (isEscape && $('#prayer-pop-welcome-modal').hasClass('is-open')) {
            closeWelcomeModal();
        }
    });

    // Handle notification frequency changes
    $('#prayer_pop_notification_frequency').on('change', function() {
        var frequency = $(this).val();
        var $timeField = $('input[name="prayer_pop_notification_settings[notification_time]"]').closest('tr');
        var $dayField = $('select[name="prayer_pop_notification_settings[notification_day]"]').closest('tr');

        if (frequency === 'immediately') {
            $timeField.hide();
            $dayField.hide();
        } else if (frequency === 'daily') {
            $timeField.show();
            $dayField.hide();
        } else if (frequency === 'weekly') {
            $timeField.show();
            $dayField.show();
        }
    }).trigger('change');

    $(document).on('change', '#prayer_pop_notification_show_debug_info', function() {
        toggleNotificationDebugPanel();
    });

    // Initialize notification fields state on page load
    initNotificationFields();

    // Initialize bubble icon controls
    initBubbleIconControls();

    // Initialize form tracking after setup changes are complete.
    initFormTracking();
    resetFormTrackingBaseline();
    formTrackingReady = true;
    restoreSettingsScroll();

    // Email template variable insertion functionality
    function insertVariableAtCursor(targetFieldId, variable) {
        var field = document.getElementById(targetFieldId);
        if (!field || !variable) return;
        
        var startPos = field.selectionStart;
        var endPos = field.selectionEnd;
        var fieldValue = field.value;
        
        // Insert variable at cursor position
        field.value = fieldValue.substring(0, startPos) + variable + fieldValue.substring(endPos);
        
        // Reset cursor position after the inserted variable
        var newPos = startPos + variable.length;
        field.setSelectionRange(newPos, newPos);
        field.focus();
        
        // Trigger change event to update sticky bar
        $(field).trigger('change');
    }

    // Bubble Icon Controls Initialization
    function initBubbleIconControls() {
        // Initialize range slider display
        updateRangeSliderDisplay();
        
        // Initialize Dashicon preview
        updateDashiconPreview();
    }

    // Handle Dashicon selection changes
    $(document).on('change', '#bubble_dashicon', function() {
        updateDashiconPreview();
        checkFormChanges(); // Update sticky bar
    });

    // Update Dashicon preview
    function updateDashiconPreview() {
        var selectedIcon = $('#bubble_dashicon').val();
        var $preview = $('#dashicon_preview');

        if ($('#bubble_icon_library_select').length || $preview.hasClass('prayer-pop-icon-preview-shell')) {
            return;
        }
        
        if (selectedIcon && $preview.length) {
            $preview.attr('class', 'dashicons dashicons-' + selectedIcon);
        }
    }

    // Handle range slider changes
    $(document).on('input', '#bubble_icon_size_range', function() {
        updateRangeSliderDisplay();
        checkFormChanges(); // Update sticky bar
    });

    // Update range slider value display
    function updateRangeSliderDisplay() {
        var $slider = $('#bubble_icon_size_range');
        var $display = $('#bubble_icon_size_value_display');
        
        if ($slider.length && $display.length) {
            $display.text($slider.val() + '%');
        }
    }

    // Handle variable insertion button clicks
    $(document).on('click', '.insert-variable-btn', function(e) {
        e.preventDefault();
        var targetFieldId = $(this).data('target');
        var dropdownId = targetFieldId.replace('_field', '_variables');
        var variable = $('#' + dropdownId).val();
        
        if (variable) {
            insertVariableAtCursor(targetFieldId, variable);
            // Reset dropdown
            $('#' + dropdownId).val('');
        }
    });

    // Handle variable dropdown selection - insert on selection
    $(document).on('change', '.prayer-pop-variables-dropdown', function() {
        var variable = $(this).val();
        if (variable) {
            var targetFieldId = $(this).attr('id').replace('_variables', '_field');
            insertVariableAtCursor(targetFieldId, variable);
            // Reset dropdown
            $(this).val('');
        }
    });

    // Confirm reset actions before submitting to options.php.
    $(document).on('click', '.prayer-pop-reset-submit', function(e) {
        var message = $(this).data('confirm') || 'Reset this section to defaults?';
        if (!window.confirm(message)) {
            e.preventDefault();
            return;
        }

        // Ensure reset submits return user to the currently active tab.
        var activeTab = $('.nav-tab-active').data('tab') || 'popup';
        $('input[name="prayer_pop_active_tab"]').val(activeTab);
        $('input[name="_wp_http_referer"]').val(getCurrentSettingsUrl(activeTab, window.pageYOffset || document.documentElement.scrollTop || 0));
    });

    // Email-template placeholder insertion and test-email action.
    $(document).on('click', '.prayer-pop-insert-placeholder', function(e) {
        e.preventDefault();
        var selector = $(this).attr('data-target');
        var placeholder = $(this).attr('data-placeholder') || '';
        var $field = selector ? $(selector) : $();
        if (!$field.length || !placeholder) {
            return;
        }

        var field = $field.get(0);
        var current = $field.val() || '';
        var start = typeof field.selectionStart === 'number' ? field.selectionStart : current.length;
        var end = typeof field.selectionEnd === 'number' ? field.selectionEnd : current.length;
        $field.val(current.substring(0, start) + placeholder + current.substring(end));
        field.focus();
        if (typeof field.setSelectionRange === 'function') {
            field.setSelectionRange(start + placeholder.length, start + placeholder.length);
        }
        $field.trigger('input').trigger('change');
    });

    $('#prayer-pop-send-test-email').on('click', function() {
        var config = (window.prayerPopAdmin && prayerPopAdmin.emailTemplate) || {};
        var $button = $(this);
        var sendLabel = config.sendLabel || 'Send Test Email';
        var failedMessage = config.failedMessage || 'Failed to send test email.';
        $button.prop('disabled', true).text(config.sendingLabel || 'Sending...');

        $.post(window.ajaxurl, {
            action: 'prayer_pop_send_test_email',
            _wpnonce: (window.prayerPopAdmin && prayerPopAdmin.nonce) || ''
        }).done(function(response) {
            window.alert(response && response.data ? response.data : failedMessage);
        }).fail(function() {
            window.alert(failedMessage);
        }).always(function() {
            $button.prop('disabled', false).text(sendLabel);
        });
    });

    // Text customization JSON import.
    $('#import_translations_btn').on('click', function() {
        var config = (window.prayerPopAdmin && prayerPopAdmin.textImport) || {};
        var fileInput = $('#translation_file').get(0);
        var file = fileInput && fileInput.files ? fileInput.files[0] : null;
        if (!file) {
            window.alert(config.selectFile || 'Please select a file to import.');
            return;
        }
        if (!/\.json$/i.test(file.name)) {
            window.alert(config.selectValidJson || 'Please select a valid JSON file.');
            return;
        }

        var $button = $(this);
        var originalText = $button.text();
        var formData = new FormData();
        formData.append('translation_file', file);
        formData.append('action', 'prayer_pop_import_translations');
        formData.append('_wpnonce', config.nonce || '');
        $button.prop('disabled', true).text(config.importing || 'Importing...');

        $.ajax({
            url: window.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false
        }).done(function(response) {
            if (response && response.success) {
                window.alert(response.data || config.importSuccess || 'Text fields imported successfully!');
                window.location.reload();
                return;
            }
            window.alert((config.importFailedPrefix || 'Import failed: ') + ((response && response.data) || config.unknownError || 'Unknown error.'));
            $button.prop('disabled', false).text(originalText);
            fileInput.value = '';
        }).fail(function(xhr, status, error) {
            window.alert((config.importFailedRetry || 'Import failed. Please try again.') + '\n' + error);
            $button.prop('disabled', false).text(originalText);
            fileInput.value = '';
        });
    });

	// Search and expand the Free-only Language & Text groups.
	(function initTextSearch() {
		var input = document.getElementById('prayer-pop-text-search');
		if (!input) return;
		var clear = document.getElementById('prayer-pop-text-search-clear');
		var status = document.getElementById('prayer-pop-text-search-status');
		var empty = document.getElementById('prayer-pop-text-search-empty');
		var groupsToggle = document.getElementById('prayer-pop-text-groups-toggle');

		function textGroups() {
			return Array.prototype.slice.call(document.querySelectorAll('#language-text .prayer-pop-text-group'));
		}

		function updateGroupsToggle() {
			if (!groupsToggle) return;
			var groups = textGroups();
			var allOpen = groups.length > 0 && groups.every(function(group) { return group.open; });
			groupsToggle.textContent = allOpen ? groupsToggle.dataset.closeLabel : groupsToggle.dataset.openLabel;
			groupsToggle.setAttribute('aria-expanded', allOpen ? 'true' : 'false');
		}

		function filterTextFields() {
			var query = (input.value || '').trim().toLowerCase();
			var visible = 0;
			textGroups().forEach(function(group) {
				var groupVisible = 0;
				group.querySelectorAll('.prayer-pop-text-field-row').forEach(function(row) {
					var fieldValues = Array.prototype.map.call(row.querySelectorAll('input, textarea'), function(field) { return field.value || ''; }).join(' ');
					var fieldText = ((row.getAttribute('data-text-search') || '') + ' ' + (row.textContent || '') + ' ' + fieldValues).toLowerCase();
					var matches = !query || fieldText.indexOf(query) !== -1;
					row.hidden = !matches;
					if (matches) {
						groupVisible += 1;
						visible += 1;
					}
				});
				group.hidden = !!query && groupVisible === 0;
				if (query && groupVisible > 0) group.open = true;
			});
			if (clear) clear.hidden = !query;
			if (empty) empty.hidden = !query || visible > 0;
			if (status) status.textContent = query ? (visible + (visible === 1 ? ' field found' : ' fields found')) : '';
			updateGroupsToggle();
		}

		input.addEventListener('input', filterTextFields);
		input.addEventListener('keydown', function(event) {
			if (event.key === 'Enter') event.preventDefault();
		});
		if (clear) clear.addEventListener('click', function() { input.value = ''; filterTextFields(); input.focus(); });
		if (groupsToggle) {
			groupsToggle.addEventListener('click', function() {
				var groups = textGroups();
				var open = !groups.length || !groups.every(function(group) { return group.open; });
				groups.forEach(function(group) { group.open = open; });
				updateGroupsToggle();
			});
			textGroups().forEach(function(group) { group.addEventListener('toggle', updateGroupsToggle); });
		}
		filterTextFields();
	}());

	// Search across the task-oriented settings tabs without changing saved values.
	(function initSettingsSearch() {
		var input = document.getElementById('prayer-pop-settings-search');
		var results = document.getElementById('prayer-pop-settings-search-results');
		var clear = document.getElementById('prayer-pop-settings-search-clear');
		if (!input || !results) return;

		input.addEventListener('keydown', function(event) {
			if (event.key === 'Enter') event.preventDefault();
		});

		function searchableBlocks() {
			return Array.prototype.slice.call(document.querySelectorAll('.tab-content .prayer-pop-subsection-card, .tab-content .prayer-pop-advanced-panel, .tab-content .prayer-pop-text-group'));
		}

		function blockTitle(block) {
			var title = block.querySelector('h2, h3, summary');
			return title ? title.textContent.trim() : 'Settings section';
		}

		function runSettingsSearch() {
			var query = (input.value || '').trim().toLowerCase();
			results.innerHTML = '';
			if (clear) clear.hidden = !query;
			if (!query) {
				results.hidden = true;
				return;
			}

			var matches = searchableBlocks().filter(function(block) {
				return (block.textContent || '').toLowerCase().indexOf(query) !== -1;
			}).slice(0, 30);
			var heading = document.createElement('strong');
			heading.textContent = matches.length + (matches.length === 1 ? ' matching section' : ' matching sections');
			results.appendChild(heading);
			var list = document.createElement('ul');
			matches.forEach(function(block) {
				var tab = block.closest('.tab-content');
				var item = document.createElement('li');
				var button = document.createElement('button');
				button.type = 'button';
				button.className = 'button-link';
				button.textContent = (tab ? tab.getAttribute('data-tab-label') + ': ' : '') + blockTitle(block);
				button.addEventListener('click', function() {
					if (tab) showTab(tab.id, true);
					if (block.tagName === 'DETAILS') block.open = true;
					block.scrollIntoView({behavior: 'smooth', block: 'start'});
					block.classList.add('prayer-pop-search-highlight');
					window.setTimeout(function() { block.classList.remove('prayer-pop-search-highlight'); }, 1800);
				});
				item.appendChild(button);
				list.appendChild(item);
			});
			results.appendChild(list);
			results.hidden = false;
		}

		input.addEventListener('input', runSettingsSearch);
		if (clear) clear.addEventListener('click', function() { input.value = ''; runSettingsSearch(); input.focus(); });
	}());

    // Adapt the feedback form to the selected report type.
    var feedbackType = document.getElementById('prayer-pop-feedback-type');
    var feedbackTitle = document.getElementById('prayer-pop-feedback-title');
    var feedbackTitleLabel = document.getElementById('prayer-pop-feedback-title-label');
    var feedbackDescription = document.getElementById('prayer-pop-feedback-description');
    var feedbackDescriptionLabel = document.getElementById('prayer-pop-feedback-description-label');
    var feedbackStepsRow = document.getElementById('prayer-pop-feedback-steps-row');
    var feedbackConfig = window.prayerPopAdmin && window.prayerPopAdmin.feedbackForm ? window.prayerPopAdmin.feedbackForm : {};

    function updateFeedbackForm() {
        if (!feedbackType) {
            return;
        }
        var selectedType = feedbackType.value;
        var prefix = selectedType === 'feature request' ? 'feature' : (selectedType === 'question' ? 'question' : 'bug');
        var defaults = {
            bug: ['Bug summary', 'A short summary of the problem', 'What happened? What did you expect?', 'Describe what went wrong and what you expected to happen.'],
            feature: ['Feature idea', 'A short name for your idea', 'What would you like PrayerPop to do?', 'Describe the feature and how it would help you.'],
            question: ['Question', 'What is your question about?', 'How can we help?', 'Write your question and include any helpful details.']
        };
        var values = defaults[prefix];
        feedbackTitleLabel.textContent = feedbackConfig[prefix + 'Title'] || values[0];
        feedbackTitle.placeholder = feedbackConfig[prefix + 'TitlePlaceholder'] || values[1];
        feedbackDescriptionLabel.textContent = feedbackConfig[prefix + 'Description'] || values[2];
        feedbackDescription.placeholder = feedbackConfig[prefix + 'DescriptionPlaceholder'] || values[3];
        feedbackStepsRow.hidden = prefix !== 'bug';
    }

    if (feedbackType && feedbackTitle && feedbackTitleLabel && feedbackDescription && feedbackDescriptionLabel && feedbackStepsRow) {
        feedbackType.addEventListener('change', updateFeedbackForm);
        updateFeedbackForm();
    }

    // Capture browser details for the feedback environment summary.
    var userAgentField = document.getElementById('prayer-pop-feedback-user-agent');
    var viewportField = document.getElementById('prayer-pop-feedback-viewport');
    var platformField = document.getElementById('prayer-pop-feedback-platform');
    var currentUrlField = document.getElementById('prayer-pop-feedback-current-url');
    var environmentPreview = document.getElementById('prayer-pop-feedback-env-preview');
    if (userAgentField) {
        userAgentField.value = window.navigator.userAgent || '';
    }
    if (viewportField) {
        viewportField.value = (window.innerWidth || 0) + 'x' + (window.innerHeight || 0);
    }
    if (platformField) {
        platformField.value = window.navigator.platform || '';
    }
    if (currentUrlField) {
        currentUrlField.value = window.location.href || '';
    }
    if (environmentPreview) {
        var environmentValues = {
            'User agent:': userAgentField && userAgentField.value,
            'Viewport:': viewportField && viewportField.value,
            'Platform:': platformField && platformField.value,
            'Current URL:': currentUrlField && currentUrlField.value
        };
        environmentPreview.textContent = environmentPreview.textContent.split('\n').map(function(line) {
            var label = Object.keys(environmentValues).find(function(candidate) {
                return line.indexOf(candidate) === 0;
            });
            return label && environmentValues[label] ? label.padEnd(20, ' ') + environmentValues[label] : line;
        }).join('\n');
    }
}); 
