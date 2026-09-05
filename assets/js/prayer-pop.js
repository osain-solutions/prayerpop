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

    var selectedAnimation = prayerPopAjax.selected_animation || 'gentle-rise';
    var validAnimations = ['none', 'fade-in', 'gentle-rise', 'soft-scale', 'slide-up', 'bounce-in'];
    if (validAnimations.indexOf(selectedAnimation) === -1) {
        selectedAnimation = 'gentle-rise';
    }

    window.PrayerPopMotion = window.PrayerPopMotion || (function () {
        var motionClasses = ['none', 'fade-in', 'gentle-rise', 'soft-scale', 'slide-up', 'bounce-in'];
        var stateClasses = ['prayerpop-motion-enter', 'prayerpop-motion-exit'];

        function reduced() {
            return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        }

        function reset(element) {
            motionClasses.concat(stateClasses).forEach(function (className) {
                element.classList.remove(className);
            });
        }

        function play(element, direction, complete) {
            if (!element) {
                if (complete) complete();
                return;
            }
            reset(element);
            element.classList.add(selectedAnimation);
            if (selectedAnimation === 'none' || reduced()) {
                if (complete) complete();
                return;
            }
            void element.offsetWidth;
            var stateClass = direction === 'exit' ? 'prayerpop-motion-exit' : 'prayerpop-motion-enter';
            var finished = false;
            var finish = function (event) {
                if (event && event.target !== element) return;
                if (finished) return;
                finished = true;
                element.removeEventListener('animationend', finish);
                element.classList.remove(stateClass);
                if (complete) complete();
            };
            element.addEventListener('animationend', finish);
            element.classList.add(stateClass);
            window.setTimeout(finish, 340);
        }

        return {
            enter: function (element) { play(element, 'enter'); },
            exit: function (element, complete) { play(element, 'exit', complete); },
            reset: reset,
            selected: selectedAnimation
        };
    }());

    /*
     * Shared shell-height transition for every PrayerPop surface that swaps
     * content in place. Keeping the larger of the two heights as an inline
     * max-height while animating prevents CSS caps from collapsing a taller
     * outgoing screen before a downward transition can be painted.
     */
    window.PrayerPopPanelTransition = window.PrayerPopPanelTransition || (function () {
        var activeTransitions = new WeakMap();

        function reduced() {
            return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        }

        function betweenHeights(element, sourceHeight, targetHeight, complete) {
            if (!element) {
                if (typeof complete === 'function') complete();
                return;
            }

            var existing = activeTransitions.get(element);
            if (existing) existing.finish();

            sourceHeight = Math.max(0, Number(sourceHeight) || 0);
            targetHeight = Math.max(0, Number(targetHeight) || 0);
            if (!sourceHeight) sourceHeight = element.getBoundingClientRect().height;
            if (!targetHeight) targetHeight = sourceHeight;

            var originalHeight = element.style.height;
            var originalMaxHeight = element.style.maxHeight;
            var originalOverflow = element.style.overflow;
            var originalTransition = element.style.transition;
            var finished = false;
            var finish = function () {
                if (finished) return;
                finished = true;
                if (activeTransitions.get(element) && activeTransitions.get(element).finish === finish) {
                    activeTransitions.delete(element);
                }
                element.style.height = originalHeight;
                element.style.maxHeight = originalMaxHeight;
                element.style.overflow = originalOverflow;
                element.style.transition = originalTransition;
                if (typeof complete === 'function') complete();
            };

            activeTransitions.set(element, {finish: finish});
            element.style.overflow = 'hidden';
            element.style.maxHeight = Math.max(sourceHeight, targetHeight) + 'px';
            element.style.height = sourceHeight + 'px';

            if (reduced() || Math.abs(targetHeight - sourceHeight) <= 1) {
                element.style.height = targetHeight + 'px';
                finish();
                return;
            }

            element.style.transition = 'height 220ms cubic-bezier(.2, .8, .2, 1)';
            void element.offsetHeight;
            window.requestAnimationFrame(function () {
                if (finished) return;
                element.style.height = targetHeight + 'px';
                window.setTimeout(finish, 250);
            });
        }

        return {betweenHeights: betweenHeights};
    }());
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
    var lastFocusedElement = null;
    var modalClosing = false;

    if (!$bubbleElement.length || !$modalElement.length) {
        return;
    }

    var popupHeightStart = null;
    var popupHeightFrame = 0;
    var popupHeightTimer = 0;
    var popupHeightOverflow = null;

    function beginPopupHeightTransition() {
        var container = document.getElementById('prayer-pop-form-container');
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!container || !$modalElement.is(':visible') || reduceMotion) {
            popupHeightStart = null;
            return;
        }

        if (popupHeightFrame) window.cancelAnimationFrame(popupHeightFrame);
        window.clearTimeout(popupHeightTimer);
        if (popupHeightOverflow === null) popupHeightOverflow = container.style.overflow;
        popupHeightStart = container.getBoundingClientRect().height;
        container.style.height = popupHeightStart + 'px';
        container.style.overflow = 'hidden';
    }

    function finishPopupHeightTransition(targetHeight) {
        if (popupHeightStart === null) return;
        var container = document.getElementById('prayer-pop-form-container');
        if (!container) return;

        if (!Number.isFinite(Number(targetHeight))) {
            container.style.height = 'auto';
            targetHeight = container.getBoundingClientRect().height;
        }
        targetHeight = Math.max(0, Number(targetHeight) || 0);
        container.style.height = popupHeightStart + 'px';
        void container.offsetHeight;
        popupHeightFrame = window.requestAnimationFrame(function () {
            popupHeightFrame = 0;
            container.style.height = targetHeight + 'px';
        });
        popupHeightTimer = window.setTimeout(function () {
            container.style.height = '';
            container.style.overflow = popupHeightOverflow || '';
            popupHeightOverflow = null;
        }, 220);
        popupHeightStart = null;
    }

    // Make the existing Classic Popup transition lifecycle available to the
    // embedded Chat screen, just as it is to Prayer Request, Testimony, and FAQ.
    window.PrayerPopLegacyPanelTransition = {
        begin: beginPopupHeightTransition,
        finish: finishPopupHeightTransition
    };

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
    if (!window.prayerPopLastTimes) {
        window.prayerPopLastTimes = {};
    }

    if (!window.prayerPopConfig || !window.prayerPopHeaders) {
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

    var popupDraftStoragePrefix = 'prayer_pop_bubble_draft:' + window.location.pathname + ':';
    var popupDraftMemory = {};

    function getPopupTypeConfig(type) {
        var types = window.prayerPopConfig.types || {};
        return types[type] || null;
    }

    function isEnabledPopupType(type) {
        return !!(getPopupTypeConfig(type) && window.prayerPopConfig.enabledTypes && window.prayerPopConfig.enabledTypes[type]);
    }

    function getPopupDraftFromStorage(type) {
        if (!isEnabledPopupType(type)) {
            return null;
        }
        if (popupDraftMemory[type] && typeof popupDraftMemory[type] === 'object') {
            return popupDraftMemory[type];
        }

        try {
            var raw = window.sessionStorage ? window.sessionStorage.getItem(popupDraftStoragePrefix + type) : '';
            if (!raw) {
                return null;
            }
            var parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object') {
                popupDraftMemory[type] = parsed;
                return parsed;
            }
        } catch (e) {
            delete popupDraftMemory[type];
        }

        return null;
    }

    function setPopupDraftToStorage(type, draft) {
        if (!isEnabledPopupType(type)) {
            return;
        }
        popupDraftMemory[type] = draft && typeof draft === 'object' ? draft : null;

        try {
            if (!window.sessionStorage) {
                return;
            }

            if (!popupDraftMemory[type]) {
                window.sessionStorage.removeItem(popupDraftStoragePrefix + type);
                return;
            }

            window.sessionStorage.setItem(popupDraftStoragePrefix + type, JSON.stringify(popupDraftMemory[type]));
        } catch (e) {
            // Ignore storage failures (private mode, blocked storage, etc.).
        }
    }

    function clearPopupDraft(type) {
        type = type || getCurrentFormType();
        setPopupDraftToStorage(type, null);
    }

    function getCurrentPopupDraft() {
        return {
            type: getCurrentFormType(),
            message: ($('#prayer-pop-form textarea[name="prayer_pop_message"]').val() || ''),
            name: ($('#prayer-pop-name').val() || '')
        };
    }

    function hasMeaningfulPopupDraft(draft) {
        if (!draft || typeof draft !== 'object') {
            return false;
        }

        if (!isEnabledPopupType(draft.type)) {
            return false;
        }

        var message = typeof draft.message === 'string' ? draft.message.trim() : '';
        var name = typeof draft.name === 'string' ? draft.name.trim() : '';

        return !!(message || name);
    }

    function persistPopupDraftFromCurrentForm() {
        var draft = getCurrentPopupDraft();
        if (hasMeaningfulPopupDraft(draft)) {
            setPopupDraftToStorage(draft.type, draft);
        } else {
            clearPopupDraft();
        }
    }

    function restorePopupDraftForType(type) {
        var draft = getPopupDraftFromStorage(type);
        if (!hasMeaningfulPopupDraft(draft) || draft.type !== type) {
            return false;
        }

        $('#prayer-pop-form input[name="prayer_pop_type"]').val(type);
        $('#prayer-pop-form textarea[name="prayer_pop_message"]').val(typeof draft.message === 'string' ? draft.message : '');
        $('#prayer-pop-name').val(typeof draft.name === 'string' ? draft.name : '');

        return true;
    }

    function restorePopupDraftOnOpen() {
        var type = getCurrentFormType();
        var draft = getPopupDraftFromStorage(type);
        if (!hasMeaningfulPopupDraft(draft)) {
            return;
        }

        restorePopupDraftForType(type);
    }

    // Templates already carry the saved class. Only correct stale/missing output so
    // document-ready does not restart the Bubble's load animation midway through.
    if (!$bubbleElement.hasClass(selectedAnimation)) {
        $bubbleElement.removeClass('none fade-in gentle-rise soft-scale slide-up bounce-in').addClass(selectedAnimation);
    }

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
        if (modalClosing) return;
        lastFocusedElement = document.activeElement;
        $('#prayer-pop-form-container').removeClass('none fade-in gentle-rise soft-scale slide-up bounce-in')
            .addClass(selectedAnimation);
        $modalElement.attr('aria-hidden', 'false');
        $bubbleElement.attr('aria-expanded', 'true');
        $('#prayer-pop-modal').stop(true, true).show();
        window.PrayerPopMotion.enter(document.getElementById('prayer-pop-form-container'));
        window.setTimeout(function() {
            var $firstFocusable = $('#prayer-pop-form-container').find('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])').filter(':visible').first();
            ($firstFocusable.length ? $firstFocusable : $('#prayer-pop-form-container')).trigger('focus');
        }, 0);

        resetPopupFormState(window.prayerPopConfig.defaultType || 'prayer_request');
        if ($('#prayer-pop-initial-options').length) {
            $('#prayer-pop-popup-intro').show();
            $('#prayer-pop-initial-options').show();
            $('#prayer-pop-form-wrapper').hide();
        } else {
            $('#prayer-pop-form-wrapper').show();
        }

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

    function applyPopupType(type) {
        var typeConfig = getPopupTypeConfig(type);
        var headerConfig = window.prayerPopHeaders[type];
        if (!typeConfig || !headerConfig || !isEnabledPopupType(type)) {
            return false;
        }

        $('#prayer-pop-form input[name="prayer_pop_type"]').val(type);
        $('#prayer-pop-header .prayer-pop-heading').text(headerConfig.header || '');
        $('#prayer-pop-description p').text(headerConfig.description || '');
        $('#prayer-pop-form textarea[name="prayer_pop_message"]').attr('placeholder', typeConfig.messagePlaceholder || '');
        $('#prayer-pop-form button[type="submit"]').text(typeConfig.submitLabel || '').removeData('original-label');
        return true;
    }

    function resetPopupFormState(typeToKeep) {
        var $form = $('#prayer-pop-form');
        if (!$form.length) {
            return;
        }

        var preservedType = (typeof typeToKeep === 'string') ? typeToKeep : getCurrentFormType();

        $form[0].reset();
        if (!applyPopupType(preservedType)) {
            applyPopupType(window.prayerPopConfig.defaultType || 'prayer_request');
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
        if (modalClosing || !$modalElement.is(':visible')) return;
        modalClosing = true;
        if ($('#prayer-pop-success').is(':visible')) {
            clearPopupDraft();
        } else {
            persistPopupDraftFromCurrentForm();
        }

        var afterClose = function() {
            $('#prayer-pop-form-container').removeClass('none fade-in gentle-rise soft-scale slide-up bounce-in');
            $('#prayer-pop-form-wrapper').show();
            $('#prayer-pop-initial-options').hide();
            $('#prayer-pop-popup-intro').hide();
            $('#prayer-pop-header').show();
            $('#prayer-pop-description').show();
            $('#prayer-pop-last-time').hide().empty();
            if (lastTimeInterval) {
                clearInterval(lastTimeInterval);
                lastTimeInterval = null;
            }
            resetPopupFormState('');
            $modalElement.attr('aria-hidden', 'true');
            $bubbleElement.attr('aria-expanded', 'false');
            if (lastFocusedElement && document.contains(lastFocusedElement)) {
                $(lastFocusedElement).trigger('focus');
            } else {
                $bubbleElement.trigger('focus');
            }
            lastFocusedElement = null;
            modalClosing = false;
        };

        window.PrayerPopMotion.exit(document.getElementById('prayer-pop-form-container'), function () {
            $('#prayer-pop-modal').stop(true, true).hide();
            afterClose();
        });
    }

    // Toggle the modal when the bubble is clicked
    $bubbleElement.on('click', function() {
        if ($('#prayer-pop-modal').is(':visible')) {
            closeModal();
        } else {
            openModal();
        }
    });

    $(document).on('keydown.prayerPopModal', function(event) {
        if (!$modalElement.is(':visible')) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal();
            return;
        }
        if (event.key !== 'Tab') return;
        var $focusable = $('#prayer-pop-form-container').find('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])').filter(':visible');
        if (!$focusable.length) {
            event.preventDefault();
            $('#prayer-pop-form-container').trigger('focus');
            return;
        }
        var first = $focusable.get(0);
        var last = $focusable.get($focusable.length - 1);
        if (event.shiftKey && (document.activeElement === first || !$.contains($modalElement.get(0), document.activeElement))) {
            event.preventDefault();
            $(last).trigger('focus');
        } else if (!event.shiftKey && (document.activeElement === last || !$.contains($modalElement.get(0), document.activeElement))) {
            event.preventDefault();
            $(first).trigger('focus');
        }
    });

    $(document).on('click', '#prayer-pop-initial-options [data-option]', function(event) {
        event.preventDefault();
        var type = String($(this).data('option') || '');
        if (!isEnabledPopupType(type)) {
            return;
        }
        beginPopupHeightTransition();
        resetPopupFormState(type);
        restorePopupDraftForType(type);
        $('#prayer-pop-popup-intro').hide();
        $('#prayer-pop-initial-options').hide();
        $('#prayer-pop-form-wrapper').show();
        finishPopupHeightTransition();
    });

    $(document).on('click', '#prayer-pop-back-button', function(event) {
        event.preventDefault();
        beginPopupHeightTransition();
        $('#prayer-pop-form-wrapper').hide();
        $('#prayer-pop-popup-intro').show();
        $('#prayer-pop-initial-options').show();
        finishPopupHeightTransition();
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

        const newRequestStyles = {
            'background-color': 'transparent',
            'color': bgColor,
            'border': '1px solid ' + bgColor,
            'border-radius': borderRadius,
            'transition': 'background-color 0.3s ease, color 0.3s ease'
        };

        $('#prayer-pop-form button[type="submit"], .prayer-pop-button').not('#prayer-pop-new-request').css(buttonStyles)
        .off('mouseenter mouseleave')
        .hover(
            function() { $(this).css('background-color', buttonHoverColor); },
            function() { $(this).css('background-color', bgColor); }
        );

        $('#prayer-pop-new-request').css(newRequestStyles)
        .off('mouseenter mouseleave')
        .hover(
            function() { $(this).css('background-color', 'color-mix(in srgb, ' + bgColor + ' 12%, transparent)'); },
            function() { $(this).css('background-color', 'transparent'); }
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
                    var typeConfig = getPopupTypeConfig(type);
                    var successMessage = (response.data && response.data.message) || (typeConfig && typeConfig.successMessage) || window.prayerPopConfig.messages.success;
                    $('#prayer-pop-success').text(successMessage).show();
                    
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
        var $textElement = $lastTime.find('.prayer-pop-last-time-text');

        if ($textElement.length === 0) {
            // Rebuild expected structure if theme/builder markup altered it.
            $lastTime.html('<span class="prayer-pop-last-time-text"></span>');
            $textElement = $lastTime.find('.prayer-pop-last-time-text');
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
