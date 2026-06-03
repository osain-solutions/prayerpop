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

    // Function to show the selected tab content
    function showTab(tabId) {
        if (!tabId || $('#' + tabId).length === 0) {
            tabId = 'general';
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
        
        // Update URL without page reload
        var newUrl = new URL(window.location.href);
        newUrl.searchParams.set('tab', tabId);
        window.history.pushState({ path: newUrl.href }, '', newUrl.href);

        // Trigger resize event to fix any layout issues
        $(window).trigger('resize');

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
        showTab(tabId);
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

    // Show initial tab from URL or default to 'general'
    var urlParams = new URLSearchParams(window.location.search);
    var initialTab = urlParams.get('tab') || 'general';
    showTab(initialTab);

    // Handle form submission
    $('#prayer-pop-settings-form').on('submit', function(e) {
        // Store the active tab
        var activeTab = $('.nav-tab-active').data('tab');
        $('input[name="prayer_pop_active_tab"]').val(activeTab);
        
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
            var tab = urlParams.get('tab') || 'general';
            showTab(tab);
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
        var activeTab = $('.nav-tab-active').data('tab') || 'general';
        $('input[name="prayer_pop_active_tab"]').val(activeTab);
    });
}); 
