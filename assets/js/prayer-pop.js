/* assets/js/prayer-pop.js */

function debugLog() {
    if (window.prayerPopConfig && window.prayerPopConfig.debug) {
        console.log.apply(console, arguments);
    }
}

jQuery(document).ready(function($) {
    if (!window.prayerPopAjax) {
        return;
    }

    var selectedAnimation = prayerPopAjax.selected_animation || 'fade-in';
    var validAnimations = ['none', 'fade-in', 'slide-up', 'bounce-in'];
    if (validAnimations.indexOf(selectedAnimation) === -1) {
        selectedAnimation = 'fade-in';
    }
    var lastTimeInterval;
    
    function keepFirstById(id) {
        var $elements = $('#' + id);
        if ($elements.length > 1) {
            $elements.slice(1).remove();
        }
        return $elements.first();
    }

    function keepModuleInstanceById(id) {
        var $elements = $('#' + id);
        if ($elements.length <= 1) {
            return $elements.first();
        }

        var $anchor = $('[data-prayer-pop-module-anchor]').first();
        if (!$anchor.length) {
            return keepFirstById(id);
        }

        var anchorEl = $anchor.get(0);
        var preferred = null;

        $elements.each(function() {
            var current = this;
            if (!preferred && (current.compareDocumentPosition(anchorEl) & 2)) {
                preferred = current;
            }
        });

        if (!preferred) {
            preferred = $elements.get(0);
        }

        $elements.each(function() {
            if (this !== preferred) {
                $(this).remove();
            }
        });

        return $(preferred);
    }

    var $moduleAnchor = $('[data-prayer-pop-module-anchor]').first();
    var hasModuleAnchor = $moduleAnchor.length > 0;
    var $bubbleElement = hasModuleAnchor ? keepModuleInstanceById('prayer-pop-bubble') : keepFirstById('prayer-pop-bubble');
    var $modalElement = hasModuleAnchor ? keepModuleInstanceById('prayer-pop-modal') : keepFirstById('prayer-pop-modal');

    if (!$bubbleElement.length || !$modalElement.length) {
        return;
    }

    // When rendered inside Theme Builder content, move floating UI to <body>
    // to avoid parent transforms breaking fixed positioning.
    if (hasModuleAnchor) {
        var $moduleContainer = $moduleAnchor.closest('.prayerpop_bubble_module__inner');
        if ($moduleContainer.length && window.getComputedStyle) {
            var moduleStyles = window.getComputedStyle($moduleContainer.get(0));
            var moduleGap = moduleStyles.getPropertyValue('--global-margin');
            var moduleCheckboxGap = moduleStyles.getPropertyValue('--checkbox-margin');
            if (moduleGap) {
                moduleGap = String(moduleGap).trim();
                if (moduleGap) {
                    $modalElement.get(0).style.setProperty('--global-margin', moduleGap);
                }
            }
            if (moduleCheckboxGap) {
                moduleCheckboxGap = String(moduleCheckboxGap).trim();
                if (moduleCheckboxGap) {
                    $modalElement.get(0).style.setProperty('--checkbox-margin', moduleCheckboxGap);
                }
            }
        }

        if ($bubbleElement.parent()[0] !== document.body) {
            $bubbleElement.appendTo(document.body);
        }
        if ($modalElement.parent()[0] !== document.body) {
            $modalElement.appendTo(document.body);
        }
    }

    if (!window.prayerPopConfig && prayerPopAjax.config) {
        window.prayerPopConfig = prayerPopAjax.config;
    }
    if (!window.prayerPopHeaders && prayerPopAjax.headers) {
        window.prayerPopHeaders = prayerPopAjax.headers;
    }
    if (!window.prayerPopLastTimes && prayerPopAjax.last_times) {
        window.prayerPopLastTimes = prayerPopAjax.last_times;
    }

    if (!window.prayerPopConfig || !window.prayerPopHeaders || !window.prayerPopLastTimes) {
        return;
    }

    // Set the spam-protection start timestamp
    function setPrayerPopStartTime() {
        var $field = $('input[name="prayer_pop_start_time"]');
        if ($field.length) {
            $field.val(Math.floor(Date.now() / 1000));
        }
    }

    function isLastSubmissionEnabled() {
        if (!window.prayerPopConfig) {
            return true;
        }

        if (typeof window.prayerPopConfig.enableLastSubmissionTime !== 'undefined') {
            return !!window.prayerPopConfig.enableLastSubmissionTime;
        }

        if (typeof window.prayerPopConfig.showLastSubmission !== 'undefined') {
            return !!window.prayerPopConfig.showLastSubmission;
        }

        if (window.prayerPopAjax) {
            if (typeof window.prayerPopAjax.enable_last_submission_time !== 'undefined') {
                return !!window.prayerPopAjax.enable_last_submission_time;
            }
            if (typeof window.prayerPopAjax.show_last_submission !== 'undefined') {
                return !!window.prayerPopAjax.show_last_submission;
            }
        }

        return true;
    }

    function normalizeUnixTimestamp(rawTimestamp) {
        var timestamp = 0;
        var now = Math.floor(Date.now() / 1000);

        if (typeof rawTimestamp === 'number' && isFinite(rawTimestamp)) {
            timestamp = rawTimestamp;
        } else if (typeof rawTimestamp === 'string') {
            var trimmed = String(rawTimestamp).trim();
            if (!trimmed) {
                return 0;
            }

            if (/^-?\d+(\.\d+)?$/.test(trimmed)) {
                timestamp = parseFloat(trimmed);
            } else {
                var parsedMillis = Date.parse(trimmed);
                if (!isNaN(parsedMillis)) {
                    timestamp = parsedMillis / 1000;
                }
            }
        }

        if (!isFinite(timestamp)) {
            return 0;
        }

        timestamp = Math.floor(timestamp);

        // Convert milliseconds to seconds.
        if (timestamp > 9999999999) {
            timestamp = Math.floor(timestamp / 1000);
        }

        // Reject invalid/ancient/far-future values.
        if (timestamp < 946684800 || timestamp > (now + 86400)) {
            return 0;
        }

        return timestamp;
    }

    var popupDraftStorageKey = 'prayer_pop_bubble_draft:' + window.location.pathname;
    var popupDraftMemory = null;

    function getPopupDraftFromStorage() {
        if (popupDraftMemory && typeof popupDraftMemory === 'object') {
            return popupDraftMemory;
        }

        try {
            var raw = window.sessionStorage ? window.sessionStorage.getItem(popupDraftStorageKey) : '';
            if (!raw) {
                return null;
            }
            var parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object') {
                popupDraftMemory = parsed;
                return parsed;
            }
        } catch (e) {
            popupDraftMemory = null;
        }

        return null;
    }

    function setPopupDraftToStorage(draft) {
        popupDraftMemory = draft && typeof draft === 'object' ? draft : null;

        try {
            if (!window.sessionStorage) {
                return;
            }

            if (!popupDraftMemory) {
                window.sessionStorage.removeItem(popupDraftStorageKey);
                return;
            }

            window.sessionStorage.setItem(popupDraftStorageKey, JSON.stringify(popupDraftMemory));
        } catch (e) {
            // Ignore storage failures (private mode, blocked storage, etc.).
        }
    }

    function clearPopupDraft() {
        setPopupDraftToStorage(null);
    }

    function getCurrentPopupDraft() {
        return {
            type: 'prayer_request',
            message: ($('#prayer-pop-form textarea[name="prayer_pop_message"]').val() || ''),
            name: ($('#prayer-pop-name').val() || '')
        };
    }

    function hasMeaningfulPopupDraft(draft) {
        if (!draft || typeof draft !== 'object') {
            return false;
        }

        if (draft.type !== 'prayer_request') {
            return false;
        }

        var message = typeof draft.message === 'string' ? draft.message.trim() : '';
        var name = typeof draft.name === 'string' ? draft.name.trim() : '';

        return !!(message || name);
    }

    function persistPopupDraftFromCurrentForm() {
        var draft = getCurrentPopupDraft();
        if (hasMeaningfulPopupDraft(draft)) {
            setPopupDraftToStorage(draft);
        } else {
            clearPopupDraft();
        }
    }

    function restorePopupDraftForType(type) {
        var draft = getPopupDraftFromStorage();
        if (!hasMeaningfulPopupDraft(draft) || draft.type !== type) {
            return false;
        }

        $('#prayer-pop-form input[name="prayer_pop_type"]').val(type);
        $('#prayer-pop-form textarea[name="prayer_pop_message"]').val(typeof draft.message === 'string' ? draft.message : '');
        $('#prayer-pop-name').val(typeof draft.name === 'string' ? draft.name : '');

        return true;
    }

    function restorePopupDraftOnOpen() {
        var draft = getPopupDraftFromStorage();
        if (!hasMeaningfulPopupDraft(draft)) {
            return;
        }

        if (draft.type !== 'prayer_request') {
            return;
        }

        restorePopupDraftForType('prayer_request');
    }

    // Apply the animation class to the bubble (reset first to avoid stale classes)
    $bubbleElement.removeClass('none fade-in slide-up bounce-in').addClass(selectedAnimation);

    // If Last Prayer Time is enabled and you want to display it immediately (for testing),
    // you can call updateLastTime for a default option (e.g. "prayer_request").
    if (isLastSubmissionEnabled()) {
        // For testing, assume "prayer_request" is the default option.
        // Ensure that prayerPopLastTimes has a value for "prayer_request".
        if (typeof prayerPopLastTimes !== 'undefined' && prayerPopLastTimes['prayer_request'] && normalizeUnixTimestamp(prayerPopLastTimes['prayer_request'].timestamp)) {
            updateLastTime('prayer_request');
            $('#prayer-pop-last-time').show();
        } else {
            $('#prayer-pop-last-time').hide();
        }
    } else {
        $('#prayer-pop-last-time').hide();
    }

    // Function to open the modal with animation
    function openModal() {
        $('#prayer-pop-form-container').removeClass('none fade-in slide-up bounce-in')
            .addClass(selectedAnimation);
        if (selectedAnimation === 'none') {
            $('#prayer-pop-modal').stop(true, true).show();
        } else {
            $('#prayer-pop-modal').stop(true, true).fadeIn();
        }

        resetPopupFormState('prayer_request');
        $('#prayer-pop-form input[name="prayer_pop_type"]').val('prayer_request');
        $('#prayer-pop-form-wrapper').show();

        // Ensure the form container is properly positioned
        var $bubble = $('#prayer-pop-bubble');
        var $container = $('#prayer-pop-form-container');
        if ($bubble.length && $container.length) {
            var bubbleHeight = $bubble.outerHeight();
            var bubbleRect = $bubble[0].getBoundingClientRect();
            var bubblePosition = String($bubble.attr('data-bubble-position') || $modalElement.attr('data-bubble-position') || 'right').toLowerCase();
            var rightOffset = Math.max(window.innerWidth - bubbleRect.right, 0);
            var leftOffset = Math.max(bubbleRect.left, 0);
            var bottomOffset = Math.max(window.innerHeight - bubbleRect.bottom, 0);

            if (bubblePosition === 'left') {
                $container.css({
                    'bottom': (bottomOffset + bubbleHeight + 20) + 'px',
                    'left': leftOffset + 'px',
                    'right': 'auto'
                });
            } else {
                $container.css({
                    'bottom': (bottomOffset + bubbleHeight + 20) + 'px',
                    'right': rightOffset + 'px',
                    'left': 'auto'
                });
            }
        }

        restorePopupDraftOnOpen();
    }

    function getCurrentFormType() {
        return $('#prayer-pop-form input[name="prayer_pop_type"]').val() || 'prayer_request';
    }

    function resetPopupFormState(typeToKeep) {
        var $form = $('#prayer-pop-form');
        if (!$form.length) {
            return;
        }

        var preservedType = (typeof typeToKeep === 'string') ? typeToKeep : getCurrentFormType();

        $form[0].reset();
        if (preservedType) {
            $form.find('input[name="prayer_pop_type"]').val(preservedType);
        }

        setPrayerPopStartTime();

        // Explicitly restore full form visibility/state.
        $form.css('display', 'flex').show();
        $('#prayer-pop-header').show();
        $('#prayer-pop-description').show();
        $('#prayer-pop-name-container').show();
        $('#prayer-pop-form textarea').show();
        $('#prayer-pop-form button[type="submit"]').show().prop('disabled', false);

        $('#prayer-pop-success').hide().empty();
        $('#prayer-pop-error').hide().empty();
        $('#prayer-pop-new-request').remove();
    }

    // Function to close the modal
    function closeModal() {
        if ($('#prayer-pop-success').is(':visible')) {
            clearPopupDraft();
        } else {
            persistPopupDraftFromCurrentForm();
        }

        var afterClose = function() {
            $('#prayer-pop-form-container').removeClass('none fade-in slide-up bounce-in');
            $('#prayer-pop-form-wrapper').show();
            $('#prayer-pop-header').show();
            $('#prayer-pop-description').show();
            $('#prayer-pop-last-time').hide().empty();
            if (lastTimeInterval) {
                clearInterval(lastTimeInterval);
                lastTimeInterval = null;
            }
            resetPopupFormState('');
        };

        if (selectedAnimation === 'none') {
            $('#prayer-pop-modal').stop(true, true).hide();
            afterClose();
        } else {
            $('#prayer-pop-modal').stop(true, true).fadeOut(afterClose);
        }
    }

    // Toggle the modal when the bubble is clicked
    $bubbleElement.on('click', function() {
        if ($('#prayer-pop-modal').is(':visible')) {
            closeModal();
        } else {
            openModal();
        }
    });

    // Close the modal when clicking outside the form container
    $('#prayer-pop-modal').on('click', function(e) {
        if ($(e.target).closest('#prayer-pop-form-container').length === 0) {
            closeModal();
        }
    });

    setPrayerPopStartTime();

    // Handle "Submit Another Request" button click
    $(document).on('click', '#prayer-pop-new-request', function() {
        clearPopupDraft();
        var selectedOption = getCurrentFormType();
        resetPopupFormState(selectedOption);
        $(this).remove();

        // Show last time message if enabled and there are previous submissions
        if (isLastSubmissionEnabled()) {
            var lastTimeData = window.prayerPopLastTimes[selectedOption];
            if (lastTimeData && normalizeUnixTimestamp(lastTimeData.timestamp)) {
                updateLastTime(selectedOption);
                $('#prayer-pop-last-time').show();
            } else {
                $('#prayer-pop-last-time').hide();
            }
        }
    });

    // Function to update button styles
    function updateButtonStyles() {
        const bgColor = getComputedStyle(document.documentElement).getPropertyValue('--global-bg-color').trim();
        const fontColor = getComputedStyle(document.documentElement).getPropertyValue('--global-font-color').trim();
        const buttonHoverColor = getComputedStyle(document.documentElement).getPropertyValue('--global-button-hover-color').trim();
        const borderRadius = getComputedStyle(document.documentElement).getPropertyValue('--global-border-radius').trim();

        const buttonStyles = {
            'background-color': bgColor,
            'color': fontColor,
            'border': 'none',
            'border-radius': borderRadius,
            'transition': 'background-color 0.3s ease'
        };

        // Apply styles to all buttons
        $('#prayer-pop-form button[type="submit"], #prayer-pop-new-request, .prayer-pop-button').css(buttonStyles)
        .off('mouseenter mouseleave')
        .hover(
            function() { $(this).css('background-color', buttonHoverColor); },
            function() { $(this).css('background-color', bgColor); }
        );
    }

    // Update button styles on load
    $(document).ready(function() {
        updateButtonStyles();
        
        // Set up MutationObserver for new request button
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.id === 'prayer-pop-new-request') {
                            updateButtonStyles();
                        }
                    });
                }
            });
        });

        // Start observing the success message container for changes
        var successElement = document.getElementById('prayer-pop-success');
        if (successElement && successElement.parentNode) {
            observer.observe(successElement.parentNode, {
                childList: true,
                subtree: true
            });
        }
    });

    // Handle form submission
    $('#prayer-pop-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $nameInput = $('#prayer-pop-name');
        var $submitButton = $form.find('button[type="submit"]');
        var type = $('input[name="prayer_pop_type"]').val();
        var originalButtonText = $submitButton.data('original-label') || $.trim($submitButton.text());
        var submittingText = (window.prayerPopConfig.messages && window.prayerPopConfig.messages.submitting)
            ? window.prayerPopConfig.messages.submitting
            : 'Sending...';

        if (!$submitButton.data('original-label')) {
            $submitButton.data('original-label', originalButtonText);
        }
        
        debugLog('[DEBUG] Submitting form with type:', type);
        
        // If name is empty and anonymous is allowed, set it to "Anonymous"
        if ($nameInput.val().trim() === '' && !$nameInput.prop('required')) {
            $nameInput.val(prayerPopAjax.anonymous_name);
        }
        
        // Disable submit button to prevent double submission
        $submitButton.prop('disabled', true);
        $submitButton.text(submittingText);
        
        $.ajax({
            url: window.prayerPopConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'prayer_pop_submit',
                nonce: window.prayerPopConfig.nonce,
                data: $form.serialize()
            },
            success: function(response) {
                debugLog('[DEBUG] Form submission response:', response);
                
                if (response.success) {
                    clearPopupDraft();
                    // Update timestamp for the current type using the server timestamp
                    if (window.prayerPopLastTimes[type] && response.data && response.data.timestamp) {
                        debugLog('[DEBUG] Updating timestamp for type:', type, 'with server timestamp:', response.data.timestamp);
                        window.prayerPopLastTimes[type].timestamp = response.data.timestamp;
                        window.prayerPopLastTimes[type].message = response.data.message_template || window.prayerPopLastTimes[type].message;
                        updateLastTime(type);
                    } else {
                        debugLog('[DEBUG] No lastTimes data or server timestamp for type:', type);
                    }
                    
                    // Hide form elements and show success message
                    $form.hide();
                    $('#prayer-pop-error').hide();
                    $('#prayer-pop-header').hide();
                    $('#prayer-pop-description').hide();
                    $('#prayer-pop-last-time').hide();
                    
                    // Show success message
                    $('#prayer-pop-success').text(window.prayerPopConfig.messages.success).show();
                    
                    // Add "Submit Another" button
                    if ($('#prayer-pop-new-request').length === 0) {
                        $('#prayer-pop-success').after(
                            '<button id="prayer-pop-new-request" class="prayer-pop-button">' + 
                            window.prayerPopConfig.messages.newRequest + 
                            '</button>'
                        );
                    }
                } else {
                    var errorMessage = (response && typeof response.data === 'string' && response.data.trim() !== '')
                        ? response.data
                        : window.prayerPopConfig.messages.error;
                    $('#prayer-pop-error').text(errorMessage).show();
                }
                
                // Update button styles
                updateButtonStyles();
            },
            error: function(xhr, status, error) {
                debugLog('[DEBUG] Form submission error:', error);
                $('#prayer-pop-error').text(window.prayerPopConfig.messages.error).show();
            },
            complete: function() {
                $submitButton.prop('disabled', false);
                $submitButton.text(originalButtonText);
            }
        });
    });

    $('#prayer-pop-form').on('input change', 'textarea[name="prayer_pop_message"], #prayer-pop-name', function() {
        persistPopupDraftFromCurrentForm();
    });

    /**
     * Function to update the last time message.
     */
    function updateLastTime(optionType) {
        debugLog('[DEBUG] updateLastTime called. optionType:', optionType);
        debugLog('[DEBUG] Current prayerPopLastTimes:', window.prayerPopLastTimes);
        
        if (!isLastSubmissionEnabled()) {
            debugLog('[DEBUG] Last submission time is disabled');
            $('#prayer-pop-last-time').hide();
            return;
        }

        var lastTimeData = window.prayerPopLastTimes[optionType];
        debugLog('[DEBUG] Last time data for', optionType, ':', lastTimeData);

        var timestamp = lastTimeData ? normalizeUnixTimestamp(lastTimeData.timestamp) : 0;
        if (!timestamp) {
            debugLog('[DEBUG] No timestamp found for', optionType, '; hiding timer.');
            $('#prayer-pop-last-time').hide();
            return;
        }

        // Calculate elapsed time in UTC (timestamps are normalized to Unix seconds).
        var utcNow = Math.floor(Date.now() / 1000);
        var diff = utcNow - timestamp;
        if (!isFinite(diff) || diff < 0) {
            diff = 0;
        }
        debugLog('[DEBUG] UTC now:', utcNow, 'Stored timestamp:', timestamp, 'Difference:', diff, 'seconds');

        var template = (lastTimeData && typeof lastTimeData.message === 'string') ? lastTimeData.message : '';
        if (!template) {
            $('#prayer-pop-last-time').hide();
            return;
        }

        var timeAgo = formatTimeAgo(diff);
        var message = template.replace('{time_ago}', timeAgo);
        
        debugLog('[DEBUG] Setting message:', message);
        
        // Ensure the timer element exists and has the correct structure
        var $lastTime = $('#prayer-pop-last-time');
        var iconSymbol = $lastTime.attr('data-badge-icon-symbol') || '⏱';
        var iconColor = $lastTime.attr('data-badge-icon-color') || '';
        var $iconElement = $lastTime.find('.prayer-pop-last-time-icon');
        var $textElement = $lastTime.find('.prayer-pop-last-time-text');

        if ($iconElement.length === 0 || $textElement.length === 0) {
            // Rebuild expected structure if theme/builder markup altered it.
            $lastTime.html('<span class="prayer-pop-last-time-icon"></span><span class="prayer-pop-last-time-text"></span>');
            $iconElement = $lastTime.find('.prayer-pop-last-time-icon');
            $textElement = $lastTime.find('.prayer-pop-last-time-text');
        }

        $iconElement.text(iconSymbol);
        if (iconColor) {
            $iconElement.css('color', iconColor);
        } else {
            $iconElement.css('color', '');
        }
        $textElement.text(message);
        
        // Force display and ensure visibility
        $lastTime.css({
            'display': 'flex',
            'visibility': 'visible',
            'opacity': '1'
        }).show();

        // Update every minute
        if (window.lastTimeInterval) {
            clearInterval(window.lastTimeInterval);
        }
        window.lastTimeInterval = setInterval(function() {
            updateLastTime(optionType);
        }, 60000);
    }

    /**
     * Format time ago in a human-readable format.
     */
    function formatTimeAgo(seconds) {
        if (!isFinite(seconds) || seconds < 0) {
            seconds = 0;
        }
        seconds = Math.floor(seconds);

        var units = window.prayerPopConfig.timeUnits;
        
        if (seconds < 60) {
            return formatTimeUnit(seconds, 'second', units);
        } else if (seconds < 3600) {
            return formatTimeUnit(Math.floor(seconds / 60), 'minute', units);
        } else if (seconds < 86400) {
            return formatTimeUnit(Math.floor(seconds / 3600), 'hour', units);
        } else {
            return formatTimeUnit(Math.floor(seconds / 86400), 'day', units);
        }
    }

    /**
     * Format time unit with proper pluralization.
     */
    function formatTimeUnit(value, unit, units) {
        var key = unit + '_' + (value === 1 ? 'singular' : 'plural');
        return value + ' ' + units[key];
    }

});
